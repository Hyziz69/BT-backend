<?php

namespace App\Http\Controllers\Api\ProgramA;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProgramA\AssignMentorRequest;
use App\Http\Resources\ProgramA\MentorshipResource;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\Mentorship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorshipController extends Controller
{
    public function index(Request $request, Application $application): JsonResponse
    {
        $this->authorizeAccess($request->user(), $application);

        $mentorships = $application->mentorships()->with(['mentor', 'consultations'])->get();

        return response()->json([
            'data' => MentorshipResource::collection($mentorships),
        ]);
    }

    public function store(AssignMentorRequest $request, Application $application): JsonResponse
    {
        $user = $request->user();
        $this->ensureAdmin($user);

        if (!in_array($application->status, ['onboarding', 'active'])) {
            return response()->json([
                'message' => 'Mentors can only be assigned to applications in onboarding or active status.',
            ], 422);
        }

        $mentor = User::where('id', $request->mentor_id)
            ->where('account_type', 'mentor')
            ->firstOrFail();

        $existing = Mentorship::where('application_id', $application->id)
            ->where('mentor_id', $mentor->id)
            ->whereNull('ended_at')
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'This mentor is already assigned to the application.'], 422);
        }

        $mentorship = Mentorship::create([
            'application_id' => $application->id,
            'mentor_id'      => $mentor->id,
            'notes'          => $request->notes,
            'started_at'     => now(),
        ]);

        AuditEvent::create([
            'actor_id'    => $user->id,
            'action'      => 'mentorship.assigned',
            'entity_type' => Mentorship::class,
            'entity_id'   => $mentorship->id,
            'payload'     => ['mentor_id' => $mentor->id, 'application_id' => $application->id],
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Mentor assigned.',
            'data'    => new MentorshipResource($mentorship->load(['mentor', 'consultations'])),
        ], 201);
    }

    public function end(Request $request, Application $application, Mentorship $mentorship): JsonResponse
    {
        $this->ensureAdmin($request->user());

        if ($mentorship->application_id !== $application->id) {
            abort(404);
        }

        $mentorship->update([
            'ended_at' => now(),
            'notes'    => $request->notes ?? $mentorship->notes,
        ]);

        return response()->json(['message' => 'Mentorship ended.']);
    }

    private function authorizeAccess($user, Application $application): void
    {
        if (in_array($user->account_type, ['nti_admin', 'superadmin', 'evaluator'])) {
            return;
        }

        $isMentor = $application->mentorships()->where('mentor_id', $user->id)->exists();
        $isMember = $application->team->members()->where('user_id', $user->id)->exists();

        if (!$isMentor && !$isMember) {
            abort(403, 'Access denied.');
        }
    }

    private function ensureAdmin($user): void
    {
        if (!in_array($user->account_type, ['nti_admin', 'superadmin'])) {
            abort(403, 'Admin access required.');
        }
    }
}
