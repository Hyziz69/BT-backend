<?php

namespace App\Http\Controllers\Api\ProgramB;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\CompanyChallenge as Challenge;
use App\Models\Evaluation;
use App\Models\Milestone;
use App\Models\Notification;
use App\Models\Team;
use App\Services\ActiveProjectGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApplicationController extends Controller
{
    public function __construct(private readonly ActiveProjectGuard $activeProjectGuard)
    {
    }

    private const TRANSITIONS = [
        'student' => [
            'draft' => ['submitted'],
            'pending_supplement' => ['submitted'],
        ],
        'company_contact' => [
            'submitted' => ['in_evaluation', 'approved', 'rejected'],
            'in_evaluation' => ['approved', 'rejected', 'pending_supplement'],
            'approved' => ['onboarding', 'active'],
            'onboarding' => ['active'],
            'active' => ['completed'],
            'completed' => ['archived'],
        ],
        'evaluator' => [
            'submitted' => ['in_evaluation'],
            'in_evaluation' => ['pending_supplement'],
        ],
        'nti_admin' => [
            'draft' => ['submitted', 'formally_verified', 'in_evaluation', 'approved', 'rejected', 'onboarding', 'active', 'paused', 'completed', 'archived'],
            'submitted' => ['formally_verified', 'in_evaluation', 'approved', 'rejected', 'onboarding', 'active', 'paused', 'completed', 'archived'],
            'formally_verified' => ['submitted', 'in_evaluation', 'pending_supplement', 'approved', 'rejected', 'onboarding', 'active', 'paused', 'completed', 'archived'],
            'in_evaluation' => ['submitted', 'formally_verified', 'pending_supplement', 'approved', 'rejected', 'onboarding', 'active', 'paused', 'completed', 'archived'],
            'pending_supplement' => ['submitted', 'formally_verified', 'in_evaluation', 'approved', 'rejected', 'onboarding', 'active', 'paused', 'completed', 'archived'],
            'approved' => ['submitted', 'formally_verified', 'in_evaluation', 'rejected', 'onboarding', 'active', 'paused', 'completed', 'archived'],
            'rejected' => ['submitted', 'formally_verified', 'in_evaluation', 'approved', 'onboarding', 'active', 'paused', 'completed', 'archived'],
            'onboarding' => ['submitted', 'formally_verified', 'in_evaluation', 'approved', 'rejected', 'active', 'paused', 'completed', 'archived'],
            'active' => ['paused', 'completed', 'archived'],
            'paused' => ['active', 'completed', 'archived'],
            'completed' => ['archived'],
            'archived' => [],
        ],
        'superadmin' => [
            'draft' => ['submitted', 'formally_verified', 'in_evaluation', 'approved', 'rejected', 'onboarding', 'active', 'paused', 'completed', 'archived'],
            'submitted' => ['formally_verified', 'in_evaluation', 'approved', 'rejected', 'onboarding', 'active', 'paused', 'completed', 'archived'],
            'formally_verified' => ['submitted', 'in_evaluation', 'pending_supplement', 'approved', 'rejected', 'onboarding', 'active', 'paused', 'completed', 'archived'],
            'in_evaluation' => ['submitted', 'formally_verified', 'pending_supplement', 'approved', 'rejected', 'onboarding', 'active', 'paused', 'completed', 'archived'],
            'pending_supplement' => ['submitted', 'formally_verified', 'in_evaluation', 'approved', 'rejected', 'onboarding', 'active', 'paused', 'completed', 'archived'],
            'approved' => ['submitted', 'formally_verified', 'in_evaluation', 'rejected', 'onboarding', 'active', 'paused', 'completed', 'archived'],
            'rejected' => ['submitted', 'formally_verified', 'in_evaluation', 'approved', 'onboarding', 'active', 'paused', 'completed', 'archived'],
            'onboarding' => ['submitted', 'formally_verified', 'in_evaluation', 'approved', 'rejected', 'active', 'paused', 'completed', 'archived'],
            'active' => ['paused', 'completed', 'archived'],
            'paused' => ['active', 'completed', 'archived'],
            'completed' => ['archived'],
            'archived' => [],
        ],
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Application::with([
            'team.members',
            'challenge.company',
            'call.program',
            'documents',
            'mentorships.mentor',
            'milestones',
        ])->whereHas('call.program', fn ($q) => $q->where('type', 'program_b'));

        if ($user->account_type === 'student') {
            $query->whereHas('team.members', fn ($q) => $q->where('users.id', $user->id));
        }

        if ($user->account_type === 'company_contact') {
            $query->whereHas('challenge.company.users', fn ($q) => $q->where('users.id', $user->id));
        }

        if ($request->filled('challenge_id')) {
            $query->where('challenge_id', $request->challenge_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'applications' => $query->latest()->get(),
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        $applications = Application::with([
            'team.members',
            'challenge.company',
            'call.program',
            'documents',
            'mentorships.mentor',
            'milestones',
        ])
            ->whereHas('call.program', fn ($q) => $q->where('type', 'program_b'))
            ->whereHas('team.members', fn ($q) => $q->where('users.id', $request->user()->id))
            ->latest()
            ->get();

        return response()->json([
            'applications' => $applications,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'challenge_id' => ['required', 'uuid', 'exists:company_challenges,id'],
            'team_id' => ['required', 'uuid', 'exists:teams,id'],
            'summary' => ['nullable', 'string', 'max:3000'],
            'solution' => ['nullable', 'string', 'max:3000'],
            'requested_budget' => ['nullable', 'numeric', 'min:0'],
        ]);

        $challenge = Challenge::with(['call.program'])
            ->where('id', $data['challenge_id'])
            ->whereHas('call.program', fn ($q) => $q->where('type', 'program_b'))
            ->firstOrFail();

        if (!$challenge->call || $challenge->call->status !== 'open') {
            return response()->json([
                'message' => 'This challenge is not open for applications.',
            ], 422);
        }

        $team = Team::where('id', $data['team_id'])
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->firstOrFail();

        $activeConflict = $this->activeProjectGuard->findConflict($team, $challenge->call);

        if ($activeConflict) {
            return response()->json([
                'message' => $this->activeProjectGuard->conflictMessage($activeConflict, $challenge->call),
            ], 422);
        }

        $application = DB::transaction(function () use ($data, $challenge, $team) {
            $duplicate = Application::where('challenge_id', $challenge->id)
                ->where('team_id', $team->id)
                ->whereNotIn('status', ['rejected', 'archived', 'completed'])
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                return null;
            }

            return Application::create([
                'call_id' => $challenge->call_id,
                'challenge_id' => $challenge->id,
                'team_id' => $team->id,
                'status' => 'submitted',
                'summary' => $data['summary'] ?? $data['motivation_letter'] ?? null,
                'problem' => $challenge->description,
                'solution' => $data['solution'] ?? null,
                'requested_budget' => $data['requested_budget'] ?? null,
                'submitted_at' => now(),
            ]);
        });

        if (!$application) {
            return response()->json([
                'message' => 'This team already applied to this challenge.',
            ], 422);
        }

        $this->notifyCompanyAboutApplication($application);

        return response()->json([
            'message' => 'Application submitted.',
            'application' => $application->load([
                'team.members',
                'challenge.company',
                'call.program',
                'documents',
                'mentorships.mentor',
                'milestones',
            ]),
        ], 201);
    }

    public function show(Request $request, Application $application): JsonResponse
    {
        $this->authorizeApplicationAccess($request, $application);

        return response()->json([
            'application' => $application->load([
                'team.members.profile',
                'challenge.company.users',
                'call.program',
                'documents',
                'mentorships.mentor',
                'milestones',
            ]),
        ]);
    }

    public function update(Request $request, Application $application): JsonResponse
    {
        $this->authorizeApplicationAccess($request, $application);

        if (!in_array($application->status, ['submitted', 'pending_supplement'], true)) {
            return response()->json([
                'message' => 'Only submitted or supplement applications can be edited.',
            ], 422);
        }

        $data = $request->validate([
            'summary' => ['required', 'string', 'max:3000'],
            'solution' => ['nullable', 'string', 'max:3000'],
            'requested_budget' => ['nullable', 'numeric', 'min:0'],
        ]);

        $application->update($data);

        return response()->json([
            'message' => 'Application updated.',
            'application' => $application->fresh([
                'team.members',
                'challenge.company',
                'call.program',
                'documents',
                'mentorships.mentor',
                'milestones',
            ]),
        ]);
    }

    public function choose(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        if ($user->account_type !== 'company_contact' && !in_array($user->account_type, ['nti_admin', 'superadmin'], true)) {
            return response()->json([
                'message' => 'Only company contacts or admins can choose a team.',
            ], 403);
        }

        $application->loadMissing(['team.members', 'call', 'challenge.company.users']);

        if (!$application->challenge || !$application->challenge->company) {
            return response()->json([
                'message' => 'This application is not connected to a company challenge.',
            ], 422);
        }

        if ($user->account_type === 'company_contact') {
            $belongsToCompany = $application->challenge->company->users()
                ->where('users.id', $user->id)
                ->exists();

            if (!$belongsToCompany) {
                return response()->json([
                    'message' => 'This challenge does not belong to your company.',
                ], 403);
            }
        }

        if (!in_array($application->status, ['submitted', 'in_evaluation', 'approved'], true)) {
            return response()->json([
                'message' => 'Only submitted or evaluated applications can be selected.',
            ], 422);
        }

        if ($application->team && $application->call) {
            $activeConflict = $this->activeProjectGuard->findConflict($application->team, $application->call);

            if ($activeConflict && $activeConflict->id !== $application->id) {
                return response()->json([
                    'message' => $this->activeProjectGuard->conflictMessage($activeConflict, $application->call),
                ], 422);
            }
        }

        DB::transaction(function () use ($application) {
            $application->update([
                'status' => 'approved',
                'decided_at' => now(),
            ]);

            if ($application->challenge) {
                $application->challenge->update([
                    'team_id' => $application->team_id,
                    'status' => 'assigned',
                ]);
            }

            Application::where('challenge_id', $application->challenge_id)
                ->where('id', '!=', $application->id)
                ->whereNotIn('status', ['rejected', 'archived', 'completed'])
                ->update([
                    'status' => 'rejected',
                    'decided_at' => now(),
                ]);

            Milestone::create([
                'application_id' => $application->id,
                'title' => 'Team selection and cooperation start',
                'status' => 'completed',
                'due_date' => now(),
                'comment' => 'Automatically created after company selected the team.',
            ]);
        });

        $this->notifyTeamMembers(
            $application,
            'team_selected',
            'Your team was selected!',
            'Your team was selected for the company challenge.'
        );

        return response()->json([
            'message' => 'Team selected and other applications rejected.',
            'application' => $application->fresh([
                'team.members',
                'challenge.company',
                'call.program',
                'documents',
                'mentorships.mentor',
                'milestones',
            ]),
        ]);
    }

    public function start(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->account_type, ['company_contact', 'nti_admin', 'superadmin'], true)) {
            return response()->json([
                'message' => 'Only company contacts or admins can start cooperation.',
            ], 403);
        }

        $application->loadMissing(['team.members', 'call', 'challenge.company.users']);

        if ($user->account_type === 'company_contact') {
            $belongsToCompany = $application->challenge?->company?->users()
                ->where('users.id', $user->id)
                ->exists();

            if (!$belongsToCompany) {
                return response()->json([
                    'message' => 'This challenge does not belong to your company.',
                ], 403);
            }
        }

        if (!in_array($application->status, ['approved', 'onboarding'], true)) {
            return response()->json([
                'message' => 'Only approved or onboarding applications can be started.',
            ], 422);
        }

        if ($application->team && $application->call) {
            $activeConflict = $this->activeProjectGuard->findConflict($application->team, $application->call);

            if ($activeConflict && $activeConflict->id !== $application->id) {
                return response()->json([
                    'message' => $this->activeProjectGuard->conflictMessage($activeConflict, $application->call),
                ], 422);
            }
        }

        $application->update([
            'status' => 'active',
        ]);

        Milestone::firstOrCreate(
            [
                'application_id' => $application->id,
                'title' => 'Project started',
            ],
            [
                'status' => 'completed',
                'due_date' => now(),
                'comment' => 'Project was started.',
            ]
        );

        $this->notifyTeamMembers(
            $application,
            'project_started',
            'Project started',
            'Your project was started.'
        );

        return response()->json([
            'message' => 'Project started.',
            'application' => $application->fresh([
                'team.members',
                'challenge.company',
                'call.program',
                'documents',
                'mentorships.mentor',
                'milestones',
            ]),
        ]);
    }

    public function transition(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:draft,submitted,formally_verified,in_evaluation,pending_supplement,approved,rejected,onboarding,active,paused,completed,archived'],
            'decision_notes' => ['nullable', 'string'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $newStatus = $validated['status'];
        $oldStatus = $application->status;

        $allowed = self::TRANSITIONS[$user->account_type][$oldStatus] ?? [];

        if (!in_array($newStatus, $allowed, true) && $oldStatus !== $newStatus) {
            return response()->json([
                'message' => "Transition from '{$oldStatus}' to '{$newStatus}' is not permitted for your role.",
            ], 422);
        }

        if ($newStatus === 'submitted') {
            $application->loadMissing('team.members.profile');

            if (!in_array($user->account_type, ['nti_admin', 'superadmin'], true)) {
                if (!$application->team || $application->team->leader_id !== $user->id) {
                    return response()->json([
                        'message' => 'Only the team leader can submit the application.',
                    ], 403);
                }
            }

            $missingCvs = [];

            if ($application->team) {
                foreach ($application->team->members as $member) {
                    if (!$member->profile || empty($member->profile->cv_path)) {
                        $missingCvs[] = $member->first_name . ' ' . $member->last_name;
                    }
                }
            }

            if (!empty($missingCvs)) {
                return response()->json([
                    'message' => 'Cannot submit. Missing CVs: ' . implode(', ', $missingCvs),
                ], 422);
            }
        }

        if (in_array($newStatus, ['approved', 'onboarding', 'active'], true)) {
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

        $application->loadMissing(['team.members', 'call']);

        DB::transaction(function () use ($application, $newStatus, $oldStatus, $validated, $user) {
            $update = [
                'status' => $newStatus,
            ];

            if ($newStatus === 'submitted' && !$application->submitted_at) {
                $update['submitted_at'] = now();
            }

            if (array_key_exists('decision_notes', $validated)) {
                $update['decision_notes'] = $validated['decision_notes'];
            }

            if (in_array($newStatus, ['approved', 'rejected'], true)) {
                $update['score'] = $validated['score'] ?? null;
                $update['decided_at'] = now();
            }

            $application->update($update);

            AuditEvent::create([
                'user_id' => $user->id,
                'action' => 'application_status_changed',
                'entity_type' => Application::class,
                'entity_id' => $application->id,
                'properties' => [
                    'from' => $oldStatus,
                    'to' => $newStatus,
                    'comment' => $validated['comment'] ?? $validated['decision_notes'] ?? null,
                ],
            ]);

            if ($application->team) {
                foreach ($application->team->members as $member) {
                    Notification::create([
                        'user_id' => $member->id,
                        'type' => 'application_status_changed',
                        'title' => 'Application status changed',
                        'message' => "Your application status changed to {$newStatus}.",
                        'data' => [
                            'application_id' => $application->id,
                            'from' => $oldStatus,
                            'to' => $newStatus,
                        ],
                    ]);

                    if ($newStatus === 'completed') {
                        Notification::create([
                            'user_id' => $member->id,
                            'type' => 'project_completed',
                            'title' => 'Project completed',
                            'message' => 'Congratulations! Your project has been marked as completed.',
                            'data' => [
                                'application_id' => $application->id,
                            ],
                        ]);
                    }
                }
            }
        });

        return response()->json([
            'message' => 'Application status updated.',
            'application' => $application->fresh([
                'team.members',
                'challenge.company',
                'call.program',
                'documents',
                'mentorships.mentor',
                'milestones',
            ]),
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
            'evaluation' => $evaluation,
        ]);
    }

    public function approveDelivery(Request $request, Application $application): JsonResponse
{
    $user = $request->user();

    if ($user->account_type !== 'company_contact' && !in_array($user->account_type, ['nti_admin', 'superadmin'], true)) {
        return response()->json(['message' => 'Only company contacts or admins can approve delivery.'], 403);
    }

    if ($user->account_type === 'company_contact') {
        $belongsToCompany = $application->challenge?->company?->users()
            ->where('users.id', $user->id)
            ->exists();

        if (!$belongsToCompany) {
            return response()->json(['message' => 'This challenge does not belong to your company.'], 403);
        }
    }

    if ($application->status === 'archived') {
        return response()->json(['message' => 'This project is already closed.'], 422);
    }

    DB::transaction(function () use ($application) {
        $application->update(['status' => 'archived']);

        if ($application->challenge) {
            $application->challenge->update(['status' => 'closed']);
        }

        Milestone::create([
            'application_id' => $application->id,
            'title'          => 'Final delivery approved',
            'status'         => 'completed',
            'due_date'       => now(),
            'comment'        => 'Company approved the final solution. Project successfully closed.',
        ]);
    });

    $this->notifyTeamMembers(
        $application,
        'project_completed',
        'Project completed 🎉',
        "Your project \"{$application->challenge?->title}\" was approved and closed by the company."
    );

    return response()->json([
        'message' => 'Project approved and closed.',
        'application' => $application->fresh([
            'team.members',
            'challenge.company',
            'call.program',
            'documents',
            'mentorships.mentor',
            'milestones',
        ]),
    ]);
}

    private function authorizeApplicationAccess(Request $request, Application $application): void
    {
        $user = $request->user();

        if (in_array($user->account_type, ['nti_admin', 'superadmin', 'evaluator', 'mentor'], true)) {
            return;
        }

        if ($user->account_type === 'student') {
            abort_unless(
                $application->team()->whereHas('members', fn ($q) => $q->where('users.id', $user->id))->exists(),
                403
            );

            return;
        }

        if ($user->account_type === 'company_contact') {
            abort_unless(
                $application->challenge()
                    ->whereHas('company.users', fn ($q) => $q->where('users.id', $user->id))
                    ->exists(),
                403
            );

            return;
        }

        abort(403);
    }

    private function notifyCompanyAboutApplication(Application $application): void
    {
        $application->loadMissing(['team', 'challenge.company.users']);

        if (!$application->challenge || !$application->challenge->company) {
            return;
        }

        foreach ($application->challenge->company->users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'application_received',
                'title' => 'New application',
                'message' => "{$application->team?->name} applied to your challenge \"{$application->challenge->title}\".",
                'data' => [
                    'application_id' => $application->id,
                    'challenge_id' => $application->challenge_id,
                ],
            ]);
        }
    }

    private function notifyTeamMembers(Application $application, string $type, string $title, string $message): void
    {
        $application->loadMissing('team.members');

        if (!$application->team) {
            return;
        }

        foreach ($application->team->members as $member) {
            Notification::create([
                'user_id' => $member->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => [
                    'application_id' => $application->id,
                    'challenge_id' => $application->challenge_id,
                ],
            ]);
        }
    }

    
}