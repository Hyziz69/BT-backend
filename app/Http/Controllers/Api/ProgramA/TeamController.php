<?php

namespace App\Http\Controllers\Api\ProgramA;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProgramA\StoreTeamRequest;
use App\Http\Requests\ProgramA\AddTeamMemberRequest;
use App\Http\Resources\ProgramA\TeamResource;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $teams = Team::with(['members', 'leader'])
            ->whereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->get();

        return response()->json([
            'data' => TeamResource::collection($teams),
        ]);
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->account_type !== 'student') {
            return response()->json(['message' => 'Only students can create teams.'], 403);
        }

        $team = DB::transaction(function () use ($request, $user) {
            $team = Team::create([
                'leader_id'    => $user->id,
                'name'         => $request->name,
                'competencies' => $request->competencies ?? [],
            ]);

            TeamMember::create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'role'    => 'leader',
            ]);

            return $team;
        });

        return response()->json([
            'message' => 'Team created successfully.',
            'data'    => new TeamResource($team->load(['members', 'leader'])),
        ], 201);
    }

    public function show(Request $request, Team $team): JsonResponse
    {
        $this->authorizeTeamAccess($request->user(), $team);

        return response()->json([
            'data' => new TeamResource($team->load(['members', 'leader'])),
        ]);
    }

    public function update(StoreTeamRequest $request, Team $team): JsonResponse
    {
        $this->authorizeTeamLeader($request->user(), $team);

        $team->update($request->only(['name', 'competencies']));

        return response()->json([
            'message' => 'Team updated.',
            'data'    => new TeamResource($team->load(['members', 'leader'])),
        ]);
    }

    public function addMember(AddTeamMemberRequest $request, Team $team): JsonResponse
    {
        $this->authorizeTeamLeader($request->user(), $team);

        $invitee = User::where('email', $request->email)
            ->where('account_type', 'student')
            ->firstOrFail();

        $alreadyMember = TeamMember::where('team_id', $team->id)
            ->where('user_id', $invitee->id)
            ->exists();

        if ($alreadyMember) {
            return response()->json(['message' => 'User is already a team member.'], 422);
        }

        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $invitee->id,
            'role'    => 'member',
        ]);

        return response()->json([
            'message' => 'Member added successfully.',
            'data'    => new TeamResource($team->load(['members', 'leader'])),
        ]);
    }

    public function removeMember(Request $request, Team $team, User $user): JsonResponse
    {
        $actor = $request->user();
        $isLeader = $team->leader_id === $actor->id;
        $isSelf   = $actor->id === $user->id;

        if (!$isLeader && !$isSelf) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($user->id === $team->leader_id) {
            return response()->json(['message' => 'Leader cannot be removed. Transfer leadership first.'], 422);
        }

        $membership = TeamMember::where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$membership) {
            return response()->json(['message' => 'User is not a member of this team.'], 404);
        }

        TeamMember::where('team_id', $team->id)
        ->where('user_id', $user->id)
        ->delete();

        return response()->json(['message' => 'Member removed.']);
    }

    private function authorizeTeamAccess(User $user, Team $team): void
    {
        if (in_array($user->account_type, ['nti_admin', 'superadmin', 'mentor', 'company_contact'])) {
            return;
        }

        $isMember = TeamMember::where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMember) {
            abort(403, 'You are not a member of this team.');
        }
    }

    private function authorizeTeamLeader(User $user, Team $team): void
    {
        if ($team->leader_id !== $user->id) {
            abort(403, 'Only the team leader can perform this action.');
        }
    }
    public function destroy(Request $request, Team $team): JsonResponse
    {
        $this->authorizeTeamLeader($request->user(), $team);

        DB::transaction(function () use ($team) {
            $team->applications()->each(function ($application) {
                $application->documents()->delete();
                $application->evaluations()->delete();
                $application->milestones()->delete();
                $application->mentorships()->delete();
                $application->delete();
            });
            TeamMember::where('team_id', $team->id)->delete();
            $team->delete();
        });

        return response()->json(['message' => 'Team deleted.']);
    }

    public function join(Request $request): JsonResponse
{
        $user = $request->user();
        
        $request->validate(['invite_code' => 'required|string']);
        
        $team = Team::where('invite_code', $request->invite_code)->firstOrFail();
        
        $alreadyMember = TeamMember::where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->exists();
            
        if ($alreadyMember) {
            return response()->json(['message' => 'You are already a member of this team.'], 422);
        }
        
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role'    => 'member',
        ]);

        \App\Models\Notification::notifyUser(
            $user->id,
            'team_joined',
            'You joined a team!',
            "You successfully joined team \"{$team->name}\"."
        );

        \App\Models\Notification::notifyUser(
            $team->leader_id,
            'member_joined',
            'New team member',
            "{$user->first_name} {$user->last_name} joined your team \"{$team->name}\"."
        );
        
        return response()->json([
            'message' => 'Joined team successfully.',
            'data'    => new TeamResource($team->load(['members', 'leader'])),
        ]);
    }
}