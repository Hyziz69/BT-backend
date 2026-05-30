<?php

namespace App\Http\Controllers\Api\ProgramA;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProgramA\StoreApplicationRequest;
use App\Http\Requests\ProgramA\UpdateApplicationRequest;
use App\Http\Requests\ProgramA\TransitionApplicationRequest;
use App\Http\Resources\ProgramA\ApplicationResource;
use App\Models\Application;
use App\Models\Call;
use App\Models\AuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApplicationController extends Controller
{
    private const TRANSITIONS = [
    'student' => [
        'draft'              => ['submitted'],
        'pending_supplement' => ['submitted'],
    ],
    'nti_admin' => [
        'submitted'          => ['formally_verified', 'rejected'],
        'formally_verified'  => ['in_evaluation', 'pending_supplement'],
        'in_evaluation'      => ['pending_supplement', 'approved', 'rejected'],
        'approved'           => ['onboarding'],
        'onboarding'         => ['active'],
        'active'             => ['paused', 'completed'],
        'paused'             => ['active', 'completed'],
        'completed'          => ['archived'],
    ],
    'evaluator' => [
        'formally_verified' => ['in_evaluation', 'pending_supplement'],
    ],
];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Application::with(['team', 'call.program', 'documents', 'evaluations'])
            ->whereHas('call.program', fn ($q) => $q->where('type', 'program_a'));

        if ($user->account_type === 'student') {
            $query->whereHas('team.members', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $applications = $query->latest()->paginate(20);

        return response()->json([
            'data'  => ApplicationResource::collection($applications),
            'meta'  => [
                'total'        => $applications->total(),
                'current_page' => $applications->currentPage(),
                'last_page'    => $applications->lastPage(),
            ],
        ]);
    }

    public function store(StoreApplicationRequest $request): JsonResponse
    {
        $user = $request->user();
        $call = Call::with('program')
            ->where('id', $request->call_id)
            ->where('status', 'open')
            ->whereHas('program', fn ($q) => $q->where('type', 'program_a'))
            ->firstOrFail();

        $team = $user->ledTeams()->findOrFail($request->team_id);

        $application = DB::transaction(function () use ($call, $team, $request) {
            $duplicate = Application::where('call_id', $call->id)
                ->where('team_id', $team->id)
                ->whereNotIn('status', ['rejected', 'archived'])
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                return null;
            }

            return Application::create([
                'call_id'           => $call->id,
                'team_id'           => $team->id,
                'challenge_id'      => null,
                'status'            => 'draft',
                'motivation_letter' => $request->motivation_letter,
                'solution_proposal' => $request->solution_proposal,
            ]);
        });

        if (!$application) {
            return response()->json([
                'message' => 'This team already has an active application for this call.',
            ], 422);
        }

        $this->auditLog($user, 'application.created', $application);

        return response()->json([
            'message' => 'Application draft created.',
            'data'    => new ApplicationResource($application->load(['team', 'call', 'documents'])),
        ], 201);
    }

    public function show(Request $request, Application $application): JsonResponse
    {
        $this->authorizeView($request->user(), $application);

        return response()->json([
            'data' => new ApplicationResource(
                $application->load(['team.members', 'call.program', 'documents', 'evaluations.evaluator', 'mentorships.mentor', 'milestones'])
            ),
        ]);
    }

    public function update(UpdateApplicationRequest $request, Application $application): JsonResponse
    {
        $user = $request->user();
        $this->authorizeTeamLeaderOfApplication($user, $application);

        if (!in_array($application->status, ['draft', 'pending_supplement'])) {
            return response()->json([
                'message' => 'Application can only be edited in draft or pending_supplement status.',
            ], 422);
        }

        $application->update($request->only(['motivation_letter', 'solution_proposal']));

        return response()->json([
            'message' => 'Application updated.',
            'data'    => new ApplicationResource($application->load(['team', 'call', 'documents'])),
        ]);
    }

    public function transition(TransitionApplicationRequest $request, Application $application): JsonResponse
    {
        $user       = $request->user();
        $newStatus  = $request->status;
        $roleKey    = $this->resolveRoleKey($user->account_type);

        if ($roleKey === 'student') {
            $this->authorizeTeamLeaderOfApplication($user, $application);
        }

        $allowed = self::TRANSITIONS[$roleKey][$application->status] ?? [];

        if (!in_array($newStatus, $allowed)) {
            return response()->json([
                'message' => "Transition from '{$application->status}' to '{$newStatus}' is not permitted for your role.",
            ], 422);
        }

        DB::transaction(function () use ($application, $newStatus, $request, $user) {
            $updates = ['status' => $newStatus];

            if ($newStatus === 'submitted') {
                $updates['submitted_at'] = now();

                $this->assertRequiredDocuments($application);
            }

            if (in_array($newStatus, ['approved', 'rejected'])) {
                $updates['decided_at']      = now();
                $updates['decision_notes']  = $request->decision_notes;
            }

            $application->update($updates);
            $this->auditLog($user, 'application.status_changed', $application, [
                'from'  => $application->getOriginal('status'),
                'to'    => $newStatus,
                'notes' => $request->decision_notes,
            ]);
        });

        return response()->json([
            'message' => "Application status changed to '{$newStatus}'.",
            'data'    => new ApplicationResource($application->fresh(['team', 'call', 'documents'])),
        ]);
    }

    private function resolveRoleKey(string $accountType): string
    {
        return match ($accountType) {
            'student', 'team_leader' => 'student',
            'nti_admin', 'superadmin' => 'nti_admin',
            'evaluator'              => 'evaluator',
            default                  => 'student',
        };
    }

    private function assertRequiredDocuments(Application $application): void
    {
        $required = [
            'executive_summary',
            'tech_architecture',
            'roadmap',
            'budget',
            'risk_analysis',
            'monetization',
        ];

        $uploaded = $application->documents()->pluck('doc_type')->toArray();
        $missing  = array_diff($required, $uploaded);

        if (!empty($missing)) {
            abort(422, 'Missing required documents: ' . implode(', ', $missing));
        }
    }

    private function authorizeView($user, Application $application): void
    {
        if (in_array($user->account_type, ['nti_admin', 'superadmin', 'evaluator', 'mentor'])) {
            return;
        }

        $isMember = $application->team->members()->where('user_id', $user->id)->exists();
        if (!$isMember) {
            abort(403, 'You do not have access to this application.');
        }
    }

    private function authorizeTeamLeaderOfApplication($user, Application $application): void
    {
        if ($application->team->leader_id !== $user->id) {
            abort(403, 'Only the team leader can modify the application.');
        }
    }

    private function auditLog($user, string $action, Application $application, array $payload = []): void
    {
        AuditEvent::create([
            'actor_id'    => $user->id,
            'action'      => $action,
            'entity_type' => Application::class,
            'entity_id'   => $application->id,
            'payload'     => $payload,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);
    }
}