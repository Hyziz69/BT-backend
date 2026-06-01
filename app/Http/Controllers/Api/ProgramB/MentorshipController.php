<?php

namespace App\Http\Controllers\Api\ProgramB;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Mentorship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorshipController extends Controller
{
    /**
     * Получение списка менторов для конкретной заявки.
     */
    public function index(Request $request, Application $application): JsonResponse
    {
        $mentorships = $application->mentorships()->with('mentor')->get();

        return response()->json([
            'data' => $mentorships
        ]);
    }

    /**
     * Назначение ментора на заявку.
     */
    public function store(Request $request, Application $application): JsonResponse
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'mentor_id' => 'required|uuid|exists:users,id',
            'notes'     => 'nullable|string'
        ]);

        // Ограничение по статусу заявки
        if (!in_array($application->status, ['approved', 'active', 'onboarding'])) {
            return response()->json([
                'message' => 'Mentors can only be assigned to applications in approved, onboarding, or active status.'
            ], 422);
        }

        $mentor = User::where('id', $validated['mentor_id'])
            ->where('account_type', 'mentor')
            ->firstOrFail();

        // Блокировка дублирования активных назначений
        $existing = Mentorship::where('application_id', $application->id)
            ->where('mentor_id', $mentor->id)
            ->whereNull('ended_at')
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'This mentor is already actively assigned to this application.'
            ], 422);
        }

        $mentorship = Mentorship::create([
            'application_id' => $application->id,
            'mentor_id'      => $mentor->id,
            'notes'          => $validated['notes'] ?? null,
            'started_at'     => now(),
        ]);

        return response()->json([
            'message' => 'Mentor assigned successfully.',
            'data'    => $mentorship->load('mentor')
        ], 201);
    }

    /**
     * Завершение (архивация) связи с ментором.
     */
    public function end(Request $request, Application $application, Mentorship $mentorship): JsonResponse
    {
        $this->ensureAdmin($request->user());

        if ($mentorship->application_id !== $application->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($mentorship->ended_at !== null) {
            return response()->json(['message' => 'Mentorship is already ended.'], 422);
        }

        $mentorship->update([
            'ended_at' => now(),
            'notes'    => $request->input('notes', $mentorship->notes),
        ]);

        return response()->json([
            'message' => 'Mentorship ended successfully.',
            'data'    => $mentorship
        ]);
    }

    /**
     * Изолированная проверка прав администратора.
     */
    private function ensureAdmin($user): void
    {
        if (!in_array($user->account_type, ['nti_admin', 'superadmin'])) {
            abort(403, 'Admin access required.');
        }
    }

}
