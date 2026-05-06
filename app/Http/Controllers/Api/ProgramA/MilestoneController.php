<?php

namespace App\Http\Controllers\Api\ProgramA;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProgramA\StoreMilestoneRequest;
use App\Http\Resources\ProgramA\MilestoneResource;
use App\Models\Application;
use App\Models\Milestone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{
    public function index(Request $request, Application $application): JsonResponse
    {
        $this->authorizeAccess($request->user(), $application);

        $milestones = $application->milestones()->orderBy('due_date')->get();

        return response()->json([
            'data' => MilestoneResource::collection($milestones),
        ]);
    }

    public function store(StoreMilestoneRequest $request, Application $application): JsonResponse
    {
        $this->authorizeAccess($request->user(), $application);

        if (!in_array($application->status, ['onboarding', 'active'])) {
            return response()->json(['message' => 'Milestones can only be added to active projects.'], 422);
        }

        $milestone = Milestone::create([
            'application_id' => $application->id,
            'title'          => $request->title,
            'status'         => 'pending',
            'due_date'       => $request->due_date,
            'comment'        => $request->comment,
        ]);

        return response()->json([
            'message' => 'Milestone created.',
            'data'    => new MilestoneResource($milestone),
        ], 201);
    }

    public function update(Request $request, Application $application, Milestone $milestone): JsonResponse
    {
        if ($milestone->application_id !== $application->id) {
            abort(404);
        }

        $user = $request->user();
        $this->authorizeUpdate($user, $application);

        $milestone->update($request->only(['status', 'comment', 'due_date', 'title']));

        return response()->json([
            'message' => 'Milestone updated.',
            'data'    => new MilestoneResource($milestone),
        ]);
    }

    public function destroy(Request $request, Application $application, Milestone $milestone): JsonResponse
    {
        if ($milestone->application_id !== $application->id) {
            abort(404);
        }

        if (!in_array($request->user()->account_type, ['nti_admin', 'superadmin'])) {
            return response()->json(['message' => 'Admin access required.'], 403);
        }

        $milestone->delete();

        return response()->json(['message' => 'Milestone deleted.']);
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

    private function authorizeUpdate($user, Application $application): void
    {
        if (in_array($user->account_type, ['nti_admin', 'superadmin'])) {
            return;
        }

        $isMentor = $application->mentorships()->where('mentor_id', $user->id)->exists();

        if (!$isMentor) {
            abort(403, 'Only mentors or admins can update milestones.');
        }
    }
}
