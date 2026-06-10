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
use App\Services\ActiveProjectGuard;

class ApplicationController extends Controller
{
    public function __construct(private readonly ActiveProjectGuard $activeProjectGuard)
    {
    }

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

        $activeConflict = $this->activeProjectGuard->findConflict($team, $call);

        if ($activeConflict) {
            return response()->json([
                'message' => $this->activeProjectGuard->conflictMessage($activeConflict, $call),
            ], 422);
        }

        $application = DB::transaction(function () use ($call, $team, $request) {
            $duplicate = Application::where('call_id', $call->id)
                ->where('team_id', $team->id)
                ->whereNotIn('status', ['rejected', 'archived'])
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                return null;
            }

            $application = Application::create([
                'call_id' => $call->id,
                'team_id' => $team->id,
                'status' => 'draft',
                'summary' => $request->summary,
                'problem' => $request->problem,
                'solution' => $request->solution,
                'requested_budget' => $request->requested_budget,
                'submitted_at' => null,
            ]);

            return $application;
        });

        if (!$application) {
            return response()->json([
                'message' => 'This team already has an active application for this call.',
            ], 422);
        }

        return response()->json([
            'message' => 'Application created.',
            'data' => new ApplicationResource($application->load(['team', 'call.program', 'documents', 'evaluations'])),
        ], 201);
    }

    public function show(Request $request, Application $application): JsonResponse
    {
        $this->authorizeView($request, $application);

        return response()->json([
            'data' => new ApplicationResource(
                $application->load(['team.members', 'call.program', 'documents', 'evaluations.evaluator', 'mentorships.mentor'])
            ),
        ]);
    }

    public function update(UpdateApplicationRequest $request, Application $application): JsonResponse
    {
        $this->authorizeView($request, $application);

        if (!in_array($application->status, ['draft', 'pending_supplement'], true)) {
            return response()->json([
                'message' => 'Only draft or supplement applications can be edited.',
            ], 422);
        }

        $application->update($request->only([
            'summary',
            'problem',
            'solution',
            'requested_budget',
        ]));

        return response()->json([
            'message' => 'Application updated.',
            'data' => new ApplicationResource($application->load(['team', 'call.program', 'documents', 'evaluations'])),
        ]);
    }

    public function transition(TransitionApplicationRequest $request, Application $application): JsonResponse
    {
        $user = $request->user();
        $toStatus = $request->status;
        $fromStatus = $application->status;

        $roleTransitions = self::TRANSITIONS[$user->account_type] ?? [];
        $allowed = $roleTransitions[$fromStatus] ?? [];

        if (!in_array($toStatus, $allowed, true) && !in_array($user->account_type, ['superadmin'], true)) {
            return response()->json([
                'message' => 'This status transition is not allowed for your role.',
            ], 403);
        }

        if (in_array($toStatus, ['approved', 'onboarding', 'active'], true)) {
            $application->loadMissing(['team.members', 'call']);

            if ($application->team && $application->call) {
                $activeConflict = $this->activeProjectGuard->findConflict($application->team, $application->call);

                if ($activeConflict && $activeConflict->id !== $application->id) {
                    return response()->json([
                        'message' => $this->activeProjectGuard->conflictMessage($activeConflict, $application->call),
                    ], 422);
                }
            }
        }

        DB::transaction(function () use ($application, $toStatus, $request, $fromStatus, $user) {
            $application->update([
                'status' => $toStatus,
                'submitted_at' => $toStatus === 'submitted' ? now() : $application->submitted_at,
                'decided_at' => in_array($toStatus, ['approved', 'rejected'], true) ? now() : $application->decided_at,
            ]);

            AuditEvent::create([
                'user_id' => $user->id,
                'action' => 'application_status_changed',
                'entity_type' => Application::class,
                'entity_id' => $application->id,
                'properties' => [
                    'from' => $fromStatus,
                    'to' => $toStatus,
                    'comment' => $request->comment,
                ],
            ]);

            if ($application->team) {
                foreach ($application->team->members as $member) {
                    Notification::create([
                        'user_id' => $member->id,
                        'type' => 'application_status_changed',
                        'title' => 'Application status changed',
                        'message' => "Your application status changed to {$toStatus}.",
                        'data' => [
                            'application_id' => $application->id,
                            'from' => $fromStatus,
                            'to' => $toStatus,
                        ],
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'Application status updated.',
            'data' => new ApplicationResource($application->fresh(['team', 'call.program', 'documents', 'evaluations'])),
        ]);
    }

    public function evaluate(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->account_type, ['evaluator', 'nti_admin', 'superadmin'], true)) {
            return response()->json([
                'message' => 'Only evaluators or admins can evaluate applications.',
            ], 403);
        }

        $data = $request->validate([
            'score_innovation' => ['required', 'integer', 'min:0', 'max:10'],
            'score_feasibility' => ['required', 'integer', 'min:0', 'max:10'],
            'score_impact' => ['required', 'integer', 'min:0', 'max:10'],
            'comment' => ['nullable', 'string', 'max:3000'],
        ]);

        $evaluation = Evaluation::updateOrCreate(
            [
                'application_id' => $application->id,
                'evaluator_id' => $user->id,
            ],
            $data
        );

        return response()->json([
            'message' => 'Evaluation saved.',
            'data' => $evaluation,
        ]);
    }

    private function authorizeView(Request $request, Application $application): void
    {
        $user = $request->user();

        if (in_array($user->account_type, ['nti_admin', 'superadmin', 'evaluator', 'mentor'], true)) {
            return;
        }

        abort_unless(
            $application->team()->whereHas('members', fn ($q) => $q->where('user_id', $user->id))->exists(),
            403
        );
    }
}