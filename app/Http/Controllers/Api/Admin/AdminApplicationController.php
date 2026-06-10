<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Mentorship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminApplicationController extends Controller
{
    private const VALID_STATUSES = [
        'draft',
        'submitted',
        'formally_verified',
        'in_evaluation',
        'pending_supplement',
        'approved',
        'onboarding',
        'active',
        'paused',
        'completed',
        'archived',
        'rejected',
    ];

    private const DECISION_STATUSES = [
        'approved',
        'rejected',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Application::with([
            'team.members',
            'challenge.company',
            'call.program',
            'documents',
            'mentorships.mentor',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('call_id')) {
            $query->where('call_id', $request->call_id);
        }

        $applications = $query->get();

        return response()->json($applications);
    }

    public function show(Application $application): JsonResponse
    {
        return response()->json(
            $application->load([
                'team.members',
                'challenge.company',
                'call.program',
                'documents',
                'evaluations',
                'mentorships.mentor',
                'milestones',
            ])
        );
    }

    public function updateStatus(Request $request, Application $application): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', self::VALID_STATUSES)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $oldStatus = $application->status;
        $newStatus = $validated['status'];

        if ($oldStatus === $newStatus) {
            return response()->json([
                'message' => 'Application already has this status.',
                'data' => $application->load([
                    'team.members',
                    'challenge.company',
                    'call.program',
                    'documents',
                    'mentorships.mentor',
                ]),
            ]);
        }

        DB::transaction(function () use ($application, $oldStatus, $newStatus, $validated) {
            $update = [
                'status' => $newStatus,
            ];

            if ($newStatus === 'submitted' && !$application->submitted_at) {
                $update['submitted_at'] = now();
            }

            if (in_array($newStatus, self::DECISION_STATUSES, true)) {
                $update['decided_at'] = now();
            }

            $application->update($update);

            if ($application->team) {
                $memberIds = $application->team->members()->pluck('users.id');

                if ($memberIds->isNotEmpty() && method_exists(\App\Models\Notification::class, 'notifyUsers')) {
                    \App\Models\Notification::notifyUsers(
                        $memberIds,
                        'application_status_changed',
                        'Application status changed',
                        "Application status changed from {$oldStatus} to {$newStatus}."
                    );
                }
            }

            if (class_exists(\App\Models\AuditEvent::class)) {
                \App\Models\AuditEvent::create([
                    'user_id' => request()->user()?->id,
                    'action' => 'admin_application_status_changed',
                    'entity_type' => Application::class,
                    'entity_id' => $application->id,
                    'properties' => [
                        'from' => $oldStatus,
                        'to' => $newStatus,
                        'comment' => $validated['comment'] ?? null,
                    ],
                ]);
            }
        });

        return response()->json([
            'message' => 'Application status updated.',
            'data' => $application->fresh([
                'team.members',
                'challenge.company',
                'call.program',
                'documents',
                'mentorships.mentor',
                'milestones',
            ]),
        ]);
    }

    public function assignMentor(Request $request, Application $application): JsonResponse
    {
        $validated = $request->validate([
            'mentor_id' => ['required', 'uuid', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $mentor = User::where('id', $validated['mentor_id'])
            ->where('account_type', 'mentor')
            ->where('status', 'active')
            ->first();

        if (!$mentor) {
            return response()->json([
                'message' => 'Selected user is not an active mentor.',
            ], 422);
        }

        if (!in_array($application->status, [
            'submitted',
            'formally_verified',
            'in_evaluation',
            'approved',
            'onboarding',
            'active',
        ], true)) {
            return response()->json([
                'message' => 'Mentor can only be assigned to submitted, evaluation, approved, onboarding or active applications.',
            ], 422);
        }

        $existing = Mentorship::where('application_id', $application->id)
            ->where('mentor_id', $mentor->id)
            ->whereNull('ended_at')
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'This mentor is already assigned to the application.',
            ], 422);
        }

        $mentorship = Mentorship::create([
            'application_id' => $application->id,
            'mentor_id' => $mentor->id,
            'notes' => $validated['notes'] ?? null,
            'started_at' => now(),
        ]);

        if ($application->team) {
            $memberIds = $application->team->members()->pluck('users.id');

            if ($memberIds->isNotEmpty() && method_exists(\App\Models\Notification::class, 'notifyUsers')) {
                \App\Models\Notification::notifyUsers(
                    $memberIds,
                    'mentor_assigned',
                    'Mentor assigned',
                    'A mentor has been assigned to your application.'
                );
            }
        }

        if (method_exists(\App\Models\Notification::class, 'notifyUser')) {
            \App\Models\Notification::notifyUser(
                $validated['mentor_id'],
                'mentor_assigned',
                'New mentorship',
                "You've been assigned as mentor for an application."
            );
        }

        return response()->json([
            'message' => 'Mentor assigned successfully.',
            'data' => $mentorship->load('mentor'),
        ], 201);
    }
}