<?php

namespace App\Http\Controllers\Api\ProgramB;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Challenge;
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

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Application::with([
            'team.members',
            'challenge.company',
            'call.program',
            'documents',
            'mentorships.mentor',
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
            'challenge_id' => ['required', 'uuid', 'exists:challenges,id'],
            'team_id' => ['required', 'uuid', 'exists:teams,id'],
            'summary' => ['required', 'string', 'max:3000'],
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
                ->whereNotIn('status', ['rejected', 'archived'])
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
                'summary' => $data['summary'],
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

        return response()->json([
            'message' => 'Application submitted.',
            'application' => $application->load([
                'team.members',
                'challenge.company',
                'call.program',
                'documents',
                'mentorships.mentor',
            ]),
        ], 201);
    }

    public function show(Request $request, Application $application): JsonResponse
    {
        $this->authorizeApplicationAccess($request, $application);

        return response()->json([
            'application' => $application->load([
                'team.members',
                'challenge.company',
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
                'message' => 'Only submitted applications can be edited.',
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
            ]),
        ]);
    }

    public function choose(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        if ($user->account_type !== 'company_contact') {
            return response()->json([
                'message' => 'Only company contacts can choose a team.',
            ], 403);
        }

        $application->loadMissing(['team.members', 'call', 'challenge.company.users']);

        if (!$application->challenge || !$application->challenge->company) {
            return response()->json([
                'message' => 'This application is not connected to a company challenge.',
            ], 422);
        }

        $belongsToCompany = $application->challenge->company->users()
            ->where('users.id', $user->id)
            ->exists();

        if (!$belongsToCompany) {
            return response()->json([
                'message' => 'This challenge does not belong to your company.',
            ], 403);
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
            Application::where('challenge_id', $application->challenge_id)
                ->where('id', '!=', $application->id)
                ->whereIn('status', ['submitted', 'in_evaluation', 'approved'])
                ->update([
                    'status' => 'rejected',
                    'decided_at' => now(),
                ]);

            $application->update([
                'status' => 'approved',
                'decided_at' => now(),
            ]);

            if ($application->team) {
                foreach ($application->team->members as $member) {
                    Notification::create([
                        'user_id' => $member->id,
                        'type' => 'program_b_application_selected',
                        'title' => 'Your team was selected',
                        'message' => 'Your team was selected for a company challenge.',
                        'data' => [
                            'application_id' => $application->id,
                            'challenge_id' => $application->challenge_id,
                        ],
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'Team selected.',
            'application' => $application->fresh([
                'team.members',
                'challenge.company',
                'call.program',
                'documents',
                'mentorships.mentor',
            ]),
        ]);
    }

    public function start(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->account_type, ['nti_admin', 'superadmin'], true)) {
            return response()->json([
                'message' => 'Only admins can start a project.',
            ], 403);
        }

        $application->loadMissing(['team.members', 'call']);

        if (!in_array($application->status, ['approved', 'onboarding'], true)) {
            return response()->json([
                'message' => 'Only approved applications can be started.',
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

        return response()->json([
            'message' => 'Project started.',
            'application' => $application->fresh([
                'team.members',
                'challenge.company',
                'call.program',
                'documents',
                'mentorships.mentor',
            ]),
        ]);
    }

    public function complete(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->account_type, ['nti_admin', 'superadmin'], true)) {
            return response()->json([
                'message' => 'Only admins can complete a project.',
            ], 403);
        }

        if (!in_array($application->status, ['active', 'paused'], true)) {
            return response()->json([
                'message' => 'Only active or paused projects can be completed.',
            ], 422);
        }

        $application->update([
            'status' => 'completed',
        ]);

        return response()->json([
            'message' => 'Project completed.',
            'application' => $application->fresh([
                'team.members',
                'challenge.company',
                'call.program',
                'documents',
                'mentorships.mentor',
            ]),
        ]);
    }

    public function reject(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->account_type, ['nti_admin', 'superadmin', 'company_contact'], true)) {
            return response()->json([
                'message' => 'Only admins or company contacts can reject applications.',
            ], 403);
        }

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

        if (in_array($application->status, ['completed', 'archived'], true)) {
            return response()->json([
                'message' => 'Completed applications cannot be rejected.',
            ], 422);
        }

        $application->update([
            'status' => 'rejected',
            'decided_at' => now(),
        ]);

        return response()->json([
            'message' => 'Application rejected.',
            'application' => $application->fresh([
                'team.members',
                'challenge.company',
                'call.program',
                'documents',
                'mentorships.mentor',
            ]),
        ]);
    }

    public function createMilestone(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->account_type, ['nti_admin', 'superadmin', 'mentor'], true)) {
            return response()->json([
                'message' => 'Only admins or mentors can create milestones.',
            ], 403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'due_date' => ['nullable', 'date'],
        ]);

        $milestone = $application->milestones()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'status' => 'open',
        ]);

        return response()->json([
            'message' => 'Milestone created.',
            'milestone' => $milestone,
        ], 201);
    }

    public function updateMilestone(Request $request, Application $application, Milestone $milestone): JsonResponse
    {
        $user = $request->user();

        if ($milestone->application_id !== $application->id) {
            return response()->json([
                'message' => 'Milestone does not belong to this application.',
            ], 404);
        }

        if (!in_array($user->account_type, ['nti_admin', 'superadmin', 'mentor'], true)) {
            return response()->json([
                'message' => 'Only admins or mentors can update milestones.',
            ], 403);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'due_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'required', 'string', 'in:open,done,blocked'],
        ]);

        $milestone->update($data);

        return response()->json([
            'message' => 'Milestone updated.',
            'milestone' => $milestone,
        ]);
    }

    public function deleteMilestone(Request $request, Application $application, Milestone $milestone): JsonResponse
    {
        $user = $request->user();

        if ($milestone->application_id !== $application->id) {
            return response()->json([
                'message' => 'Milestone does not belong to this application.',
            ], 404);
        }

        if (!in_array($user->account_type, ['nti_admin', 'superadmin', 'mentor'], true)) {
            return response()->json([
                'message' => 'Only admins or mentors can delete milestones.',
            ], 403);
        }

        $milestone->delete();

        return response()->json([
            'message' => 'Milestone deleted.',
        ]);
    }

    private function authorizeApplicationAccess(Request $request, Application $application): void
    {
        $user = $request->user();

        if (in_array($user->account_type, ['nti_admin', 'superadmin', 'mentor'], true)) {
            return;
        }

        if ($user->account_type === 'company_contact') {
            abort_unless(
                $application->challenge?->company?->users()
                    ->where('users.id', $user->id)
                    ->exists(),
                403
            );

            return;
        }

        abort_unless(
            $application->team()->whereHas('members', fn ($q) => $q->where('users.id', $user->id))->exists(),
            403
        );
    }
}