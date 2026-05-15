<?php

namespace App\Http\Controllers\Api\ProgramB;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TeamController extends Controller
{
    private function checkTeamLock(Team $team, User $user): ?string
    {
        if ($user->account_type === 'nti_admin') {
            return null;
        }

        $application = DB::table('applications')->where('team_id', $team->id)->first();

        if (!$application) {
            return null;
        }

        $hasClosedStatus = DB::table('company_challenges')
            ->where('id', $application->id)
            ->where('status', 'closed')
            ->exists();

        if ($hasClosedStatus) {
            return null;
        }


        return 'Tím už podal prihlášku. Zmeny sú uzamknuté a môže ich vykonať iba administrátor, alebo až po skončení programu (milestone = ended).';
    }
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:200'],
            'competencies'   => ['nullable', 'array'],
            'competencies.*' => ['string', 'max:50'],
        ]);

        $user = $request->user();

        $alreadyInTeam = DB::table('team_members')
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyInTeam) {
            return response()->json([
                'message' => 'Už ste členom iného tímu.'
            ], 422);
        }

//        $validated = $request->validated();

        try {
            $team = DB::transaction(function () use ($validated, $user) {

                $newTeam = Team::create([
                    'leader_id'    => $user->id,
                    'name'         => $validated['name'],
                    'competencies' => $validated['competencies'] ?? null,
                ]);

                $newTeam->members()->attach($user->id, [
                    'role'      => 'leader',
                    'joined_at' => now(),
                ]);

                return $newTeam;
            });


            return response()->json([
                'message' => 'Tím bol úspešne vytvorený!',
                'team'    => $team
            ], 201);

        } catch (\Exception $e) {
            Log::error('Chyba pri vytváraní tímu: ' . $e->getMessage());

            return response()->json([
                'message' => 'Vyskytla sa chyba pri vytváraní tímu.'
            ], 500);
        }
    }
    public function destroy(Request $request,Team $team): JsonResponse
    {
        $user = $request->user();

        if ($team->leader_id !== $user->id && $user->account_type !== 'nti_admin') {
            return response()->json([
                'message' => 'Nemáte oprávnenie vymazať tento tím.'
            ], 403);
        }

        if ($lockMessage = $this->checkTeamLock($team, $user)) {
            return response()->json(['message' => $lockMessage], 403);
        }

        try {
            DB::transaction(function () use ($team) {
                DB::table('applications')->where('team_id', $team->id)->delete();

                $team->members()->detach();

                $team->delete();
            });

            return response()->json([
                'message' => 'Tím bol úspešne odstránený.'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Chyba pri odstraňovaní tímu: ' . $e->getMessage());

            return response()->json([
                'message' => 'Vyskytla sa chyba pri odstraňovaní tímu.'
            ], 500);
        }
    }
    public function join(Request $request): JsonResponse
    {

        $user = $request->user();


        $validated = $request->validate([
            'invite_code' => ['required', 'string', 'size:8']
        ]);


        $team = Team::where('invite_code', $validated['invite_code'])->first();

        if (!$team) {
            return response()->json(['message' => 'Neplatný pozývací kód.'], 404);
        }


        $alreadyInTeam = DB::table('team_members')
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyInTeam) {
            return response()->json(['message' => 'Už ste členom nejakého tímu.'], 422);
        }

        try {
            $team->members()->attach($user->id, [
                'role'      => 'member',
                'joined_at' => now(),
            ]);

            return response()->json([
                'message' => 'Úspešne ste sa pripojili k tímu.',
                'team'    => $team
            ], 200);

        } catch (\Exception $e) {
            Log::error('Chyba pri pripájaní k tímu: ' . $e->getMessage());
            return response()->json(['message' => 'Vyskytla sa chyba servera.'], 500);
        }
    }
    public function leave(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Používateľ nenájdený.'], 404);
        }

        $isMember = $team->members()->where('user_id', $user->id)->exists();

        if (!$isMember) {
            return response()->json(['message' => 'Nie ste členom tohto tímu.'], 403);
        }

        if ($lockMessage = $this->checkTeamLock($team, $user)) {
            return response()->json(['message' => $lockMessage], 403);
        }

        try {
            DB::transaction(function () use ($team, $user) {


                if ($team->leader_id === $user->id) {


                    DB::table('applications')->where('team_id', $team->id)->delete();


                    $team->members()->detach();

                    $team->delete();

                } else {
                    $team->members()->detach($user->id);
                }
            });

            $message = $team->leader_id === $user->id
                ? 'Tím bol úspešne rozpustený, pretože ho opustil líder.'
                : 'Úspešne ste opustili tím.';

            return response()->json(['message' => $message], 200);

        } catch (\Exception $e) {
            Log::error('Chyba pri opúšťaní tímu: ' . $e->getMessage());
            return response()->json(['message' => 'Vyskytla sa chyba servera.'], 500);
        }
    }

    public function kickMember(Request $request, Team $team, User $user): JsonResponse
    {
        $actingUser = $request->user();

        $isAdmin = $actingUser->account_type === 'nti_admin';
        $isLeader = $team->leader_id === $actingUser->id;

        if (!$isLeader && !$isAdmin) {
            return response()->json(['message' => 'Iba líder tímu môže odstraňovať členov.'], 403);
        }

        if ($team->leader_id === $user->id) {
            return response()->json(['message' => 'Nemôžete odstrániť sám seba. Ak chcete tím zrušiť, použite príslušnú funkciu.'], 422);
        }

        $isMember = $team->members()->where('user_id', $user->id)->exists();

        if (!$isMember) {
            return response()->json(['message' => 'Tento používateľ nie je členom tímu.'], 404);
        }

        $hasApplication = DB::table('applications')->where('team_id', $team->id)->exists();
        if ($hasApplication && !$isAdmin) {
            return response()->json([
                'message' => 'Tím už podal prihlášku. Zloženie tímu je uzamknuté a členovia nemôžu byť odstraňovaní.'
            ], 403);
        }

        if ($lockMessage = $this->checkTeamLock($team, $actingUser)) {
            return response()->json(['message' => $lockMessage], 403);
        }

        try {
            $team->members()->detach($user->id);
            return response()->json(['message' => 'Člen bol úspešne odstránený z tímu.'], 200);

        } catch (\Exception $e) {
            Log::error('Chyba pri odstraňovaní člena tímu: ' . $e->getMessage());
            return response()->json(['message' => 'Vyskytla sa chyba servera.'], 500);
        }
    }

    public function update(Request $request, Team $team): JsonResponse
    {
        $actingUser = $request->user();

        if ($team->leader_id !== $actingUser->id && $actingUser->account_type !== 'nti_admin') {
            return response()->json(['message' => 'Nemáte oprávnenie upravovať tento tím.'], 403);
        }


        if ($lockMessage = $this->checkTeamLock($team, $actingUser)) {
            return response()->json(['message' => $lockMessage], 403);
        }


        $validatedData = $request->validate([
            'name'         => 'sometimes|required|string|max:255',
            'competencies' => 'nullable|array',
            'competencies.*' => 'string|max:50',
        ]);

        try {
            $team->update($validatedData);

            return response()->json([
                'message' => 'Tím bol úspešne aktualizovaný.',
                'team'    => $team->fresh()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Chyba pri aktualizácii tímu: ' . $e->getMessage());
            return response()->json(['message' => 'Vyskytla sa chyba servera.'], 500);
        }
    }
    public function show(Request $request, Team $team): JsonResponse
    {

        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Používateľ nenájdený.'], 404);
        }

        $isMember = $team->members()->where('user_id', $user->id)->exists();

        $allowedRoles = ['mentor', 'company_contact', 'editor', 'nti_admin', 'superadmin'];

        if (!$isMember && !in_array($user->account_type, $allowedRoles)) {
            return response()->json(['message' => 'Nemáte oprávnenie zobraziť tento tím.'], 403);
        }

        $team->load([
            'leader:id,first_name,last_name,email',
            'members:id,first_name,last_name,email'
        ]);

        if ($team->leader_id !== $user->id && $user->account_type !== 'nti_admin') {
            $team->makeHidden('invite_code');
        }

        return response()->json([
            'team' => $team
        ], 200);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();


        $allowedRoles = ['mentor', 'company_contact', 'editor', 'nti_admin', 'superadmin'];
        $hasElevatedPrivileges = in_array($user->account_type, $allowedRoles);

        $teams = Team::with([
            'leader:id,first_name,last_name,email',
            'members:id,first_name,last_name,email'
        ])->paginate(15);


        if (!$hasElevatedPrivileges) {
            $teams->getCollection()->transform(function ($team) use ($user) {
                if ($team->leader_id !== $user->id) {
                    $team->makeHidden('invite_code');
                }
                return $team;
            });
        }

        return response()->json($teams, 200);
    }
}
