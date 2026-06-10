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
<<<<<<< HEAD
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
=======
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
>>>>>>> sandbox-merge

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

    public function transition(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();
<<<<<<< HEAD

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

            // Notify on completion
            if ($newStatus === 'completed') {
                $memberIds = $application->team->members()->pluck('team_members.user_id');
                \App\Models\Notification::notifyUsers(
                    $memberIds,
                    'project_completed',
                    'Project completed 🎉',
                    "Congratulations! Your project has been marked as completed."
                );
            }

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
=======
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
>>>>>>> sandbox-merge
            }
        }

<<<<<<< HEAD
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

        // Extra notification on completion
        if ($newStatus === 'completed') {
            \App\Models\Notification::notifyUsers(
                $memberIds,
                'project_completed',
                'Project completed 🎉',
                "Congratulations! Your project has been marked as completed."
            );
        }

        return response()->json($application);
=======
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
>>>>>>> sandbox-merge
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