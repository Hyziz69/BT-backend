<?php

namespace App\Http\Controllers\Api\ProgramA;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProgramA\StoreConsultationRequest;
use App\Http\Resources\ProgramA\ConsultationResource;
use App\Models\Application;
use App\Models\Consultation;
use App\Models\Mentorship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultationController extends Controller
{
    public function index(Request $request, Application $application, Mentorship $mentorship): JsonResponse
    {
        $this->ensureMentorshipBelongs($mentorship, $application);
        $this->authorizeAccess($request->user(), $application, $mentorship);

        $consultations = $mentorship->consultations()->orderByDesc('scheduled_at')->get();

        return response()->json([
            'data' => ConsultationResource::collection($consultations),
        ]);
    }

    public function store(StoreConsultationRequest $request, Application $application, Mentorship $mentorship): JsonResponse
    {
        $this->ensureMentorshipBelongs($mentorship, $application);

        $user = $request->user();

        if ($mentorship->mentor_id !== $user->id && !in_array($user->account_type, ['nti_admin', 'superadmin'])) {
            return response()->json(['message' => 'Only the assigned mentor can log consultations.'], 403);
        }

        if (!is_null($mentorship->ended_at)) {
            return response()->json(['message' => 'Mentorship has ended.'], 422);
        }

        $consultation = Consultation::create([
            'mentorship_id' => $mentorship->id,
            'scheduled_at'  => $request->scheduled_at,
            'notes'         => $request->notes,
            'feedback'      => $request->feedback,
        ]);

        return response()->json([
            'message' => 'Consultation logged.',
            'data'    => new ConsultationResource($consultation),
        ], 201);
    }

    private function ensureMentorshipBelongs(Mentorship $mentorship, Application $application): void
    {
        if ($mentorship->application_id !== $application->id) {
            abort(404);
        }
    }

    private function authorizeAccess($user, Application $application, Mentorship $mentorship): void
    {
        if (in_array($user->account_type, ['nti_admin', 'superadmin'])) {
            return;
        }

        $isMentor = $mentorship->mentor_id === $user->id;
        $isMember = $application->team->members()->where('user_id', $user->id)->exists();

        if (!$isMentor && !$isMember) {
            abort(403, 'Access denied.');
        }
    }
}
