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
use App\Models\Evaluation;
use App\Models\Notification;

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
        'formally_verified'  => ['in_evaluation', 'pending_supplement'],
        'in_evaluation'      => ['pending_supplement'],
    ],
    'company_contact' => [
        'submitted' => ['approved'],
    ],
];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Application::with(['team', 'call.program', 'documents', 'evaluations'])
            ->whereHas('call.program', fn ($q) => $q->where('type', 'program_a'));

        $privileged = in_array($user->account_type, ['nti_admin', 'superadmin', 'evaluator', 'mentor'], true);

        if ($user->account_type === 'company_contact') {
            $query->whereNotIn('status', ['draft', 'pending_supplement']);
        } elseif (!$privileged) {
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

    public function destroy(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        if ($application->team->leader_id !== $user->id && !in_array($user->account_type, ['nti_admin', 'superadmin'])) {
            return response()->json(['message' => 'Only the team leader can delete an application.'], 403);
        }

        if (!in_array($application->status, ['draft']) && !in_array($user->account_type, ['nti_admin', 'superadmin'])) {
            return response()->json(['message' => 'Only draft applications can be deleted.'], 422);
        }

        $application->delete();

        return response()->json(['message' => 'Application deleted.']);
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

    public function transition(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'status'         => 'required|string|in:draft,submitted,formally_verified,in_evaluation,pending_supplement,approved,rejected,onboarding,active,paused,completed,archived',
            'decision_notes' => 'nullable|string',
            'score'          => 'nullable|numeric|min:0|max:100',
        ]);

        $newStatus     = $validated['status'];
        $currentStatus = $application->status;
        $isAdmin       = in_array($user->account_type, ['nti_admin', 'superadmin']);

        // Admins can force any status
        if ($isAdmin) {
            if (array_key_exists('decision_notes', $validated)) {
                $application->decision_notes = $validated['decision_notes'];
            }
            if (in_array($newStatus, ['approved', 'rejected'])) {
                $application->decided_at = now();
                $application->score = $validated['score'] ?? null;
            }
            if ($newStatus === 'submitted') {
                $application->submitted_at = now();
            }
            $application->status = $newStatus;
            $application->save();

            $application->load(['team.members', 'call.program', 'challenge', 'milestones', 'mentorships.mentor']);
            $application->setAttribute('available_transitions', self::TRANSITIONS['nti_admin'][$newStatus] ?? []);

            return response()->json($application);
        }

        // Non-admin: check allowed transitions
        $roleKey = $this->resolveRoleKey($user->account_type);
        $allowed = self::TRANSITIONS[$roleKey][$currentStatus] ?? [];

        if (!in_array($newStatus, $allowed)) {
            return response()->json([
                'message' => "Transition from '{$currentStatus}' to '{$newStatus}' is not permitted for your role.",
            ], 422);
        }

        if ($newStatus === 'submitted') {
            if ($application->team->leader_id !== $user->id) {
                return response()->json(['message' => 'Only the team leader can submit the application.'], 403);
            }

            $missingCvs = [];
            $application->loadMissing('team.members.profile');
            foreach ($application->team->members as $member) {
                if (!$member->profile || empty($member->profile->cv_path)) {
                    $missingCvs[] = $member->first_name . ' ' . $member->last_name;
                }
            }
            if (!empty($missingCvs)) {
                return response()->json([
                    'message' => 'Cannot submit. Missing CVs: ' . implode(', ', $missingCvs),
                ], 422);
            }

            $application->submitted_at = now();
        } else {
            if (array_key_exists('decision_notes', $validated)) {
                $application->decision_notes = $validated['decision_notes'];
            }
            if (in_array($newStatus, ['approved', 'rejected'])) {
                $application->score      = $validated['score'] ?? null;
                $application->decided_at = now();
            }
        }

        $application->status = $newStatus;
        $application->save();

        $application->load(['team.members', 'call.program', 'challenge', 'milestones', 'mentorships.mentor']);
        $application->setAttribute('available_transitions', self::TRANSITIONS[$roleKey][$newStatus] ?? []);

        // Notify team members of status change
        $memberIds = $application->team->members()->pluck('team_members.user_id');
        \App\Models\Notification::notifyUsers(
            $memberIds,
            'status_changed',
            'Application status updated',
            "Your application status changed to '{$newStatus}'."
        );

        return response()->json($application);
    }

    private function resolveRoleKey(string $accountType): string
    {
        return match ($accountType) {
            'student', 'team_leader' => 'student',
            'nti_admin', 'superadmin' => 'nti_admin',
            'evaluator'              => 'evaluator',
            'company_contact'        => 'company_contact',
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
        if (in_array($user->account_type, ['nti_admin', 'superadmin', 'evaluator', 'mentor', 'company_contact'])) {
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