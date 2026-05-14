<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Mentorship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Application::with([
            'team',
            'call.program',
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
                'call.program',
                'documents',
                'evaluations',
                'mentorships.mentor',
                'milestones',
            ])
        );
    }

    public function assignMentor(Request $request, Application $application): JsonResponse
    {
        $validated = $request->validate([
            'mentor_id' => ['required', 'uuid', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $mentor = User::where('id', $validated['mentor_id'])
            ->where('account_type', 'mentor')
            ->first();

        if (!$mentor) {
            return response()->json([
                'message' => 'Selected user is not a mentor.',
            ], 422);
        }

        if (!in_array($application->status, ['approved', 'onboarding', 'active'])) {
            return response()->json([
                'message' => 'Mentor can only be assigned to approved, onboarding or active applications.',
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

        return response()->json([
            'message' => 'Mentor assigned successfully.',
            'data' => $mentorship->load('mentor'),
        ], 201);
    }
}