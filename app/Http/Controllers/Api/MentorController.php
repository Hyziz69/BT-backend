<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Mentorship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorController extends Controller
{
    public function mentorships(Request $request): JsonResponse
    {
        $user = $request->user();

        $mentorships = Mentorship::where('mentor_id', $user->id)
            ->with([
                'application.team',
                'application.call.program',
                'application.challenge',
                'consultations',
            ])
            ->withCount('consultations')
            ->get();

        return response()->json(['mentorships' => $mentorships]);
    }

    public function mentorship(Request $request, Mentorship $mentorship): JsonResponse
    {
        if ($mentorship->mentor_id !== $request->user()->id) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $mentorship->load([
            'application.team.members',
            'application.call.program',
            'application.challenge',
            'application.milestones',
            'consultations',
        ]);

        return response()->json(['mentorship' => $mentorship]);
    }

    public function logConsultation(Request $request, Mentorship $mentorship): JsonResponse
    {
        if ($mentorship->mentor_id !== $request->user()->id) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'notes'        => ['nullable', 'string'],
            'feedback'     => ['nullable', 'string'],
        ]);

        $consultation = Consultation::create([
            'mentorship_id' => $mentorship->id,
            'scheduled_at'  => $validated['scheduled_at'],
            'notes'         => $validated['notes'] ?? null,
            'feedback'      => $validated['feedback'] ?? null,
        ]);

        return response()->json([
            'message'      => 'Consultation logged.',
            'consultation' => $consultation,
        ], 201);
    }
}