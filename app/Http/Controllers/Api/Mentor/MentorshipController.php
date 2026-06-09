<?php

namespace App\Http\Controllers\Api\Mentor;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Mentorship;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MentorshipController extends Controller
{
    /**
     * The current mentor's mentorships (their mentees), newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $mentorships = Mentorship::where('mentor_id', $user->id)
            ->with([
                'application.team:id,name',
                'application.challenge:id,title,status',
                'application.call.program:id,name',
            ])
            ->withCount('consultations')
            ->orderByDesc('started_at')
            ->get();

        return response()->json(['mentorships' => $mentorships]);
    }

    /**
     * One mentorship in detail: team + members, project, consultations log.
     */
    public function show(Request $request, Mentorship $mentorship): JsonResponse
    {
        if ($denied = $this->authorizeOwner($request, $mentorship)) {
            return $denied;
        }

        $mentorship->load([
            'application.team.members:id,first_name,last_name,email',
            'application.challenge:id,title,status,technical_spec,company_id',
            'application.challenge.company:id,name',
            'application.call.program:id,name',
            'application.milestones',
            'consultations' => fn ($q) => $q->orderByDesc('scheduled_at'),
        ]);

        return response()->json(['mentorship' => $mentorship]);
    }

    /**
     * Log a consultation (meeting summary / feedback) for this mentorship.
     */
    public function storeConsultation(Request $request, Mentorship $mentorship): JsonResponse
    {
        if ($denied = $this->authorizeOwner($request, $mentorship)) {
            return $denied;
        }

        $validated = $request->validate([
            'scheduled_at' => 'required|date',
            'notes'        => 'nullable|string',
            'feedback'     => 'nullable|string',
        ]);

        $consultation = Consultation::create([
            'mentorship_id' => $mentorship->id,
            'scheduled_at'  => $validated['scheduled_at'],
            'notes'         => $validated['notes'] ?? null,
            'feedback'      => $validated['feedback'] ?? null,
        ]);

        // Notify the team that their mentor logged a consultation.
        $team = $mentorship->application?->team;
        if ($team) {
            $memberIds = DB::table('team_members')->where('team_id', $team->id)->pluck('user_id');
            $mentorName = trim($request->user()->first_name . ' ' . $request->user()->last_name);
            Notification::notifyUsers(
                $memberIds,
                'consultation_logged',
                'New consultation',
                "{$mentorName} logged a consultation with your team."
            );
        }

        return response()->json([
            'message'      => 'Consultation logged.',
            'consultation' => $consultation,
        ], 201);
    }

    /**
     * Ensure the acting user owns this mentorship (or is an NTI admin).
     */
    private function authorizeOwner(Request $request, Mentorship $mentorship): ?JsonResponse
    {
        $user = $request->user();
        $isAdmin = in_array($user->account_type, ['nti_admin', 'superadmin'], true);

        if ($mentorship->mentor_id !== $user->id && ! $isAdmin) {
            return response()->json(['message' => 'This mentorship is not yours.'], 403);
        }

        return null;
    }
}
