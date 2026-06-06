<?php

namespace App\Http\Controllers\Api\ProgramB;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\CompanyChallenge;
use App\Models\Mentorship;
use App\Models\Milestone;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApplicationController extends Controller
{
    /**
     * STUDENT VIEW: the current user's own Program B applications,
     * with challenge + company + status, so the team can track progress.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $teamIds = DB::table('team_members')
            ->where('user_id', $user->id)
            ->pluck('team_id');

        $applications = Application::whereIn('team_id', $teamIds)
            ->whereNotNull('challenge_id')
            ->with(['challenge:id,title,status,company_id,budget', 'challenge.company:id,name', 'team:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'applications' => $applications,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $team = Team::with('members.profile')
            ->where('leader_id', $user->id)
            ->first();

        if (!$team) {
            return response()->json(['message' => 'Nie ste lídrom žiadneho tímu alebo tím neexistuje.'], 403);
        }

        $validated = $request->validate([
            'call_id'           => 'required|uuid|exists:calls,id',
            'challenge_id'      => 'required|uuid',
            'motivation_letter' => 'nullable|string',
            'solution_proposal' => 'nullable|string',
        ]);


        if ($team->leader_id !== $user->id) {
            return response()->json(['message' => 'Iba líder tímu môže podať prihlášku.'], 403);
        }

        // 3. Business Logic: Ensure ALL team members have a CV uploaded
        $missingCvs = [];
        foreach ($team->members as $member) {
            // Check if profile exists and if cv_url is filled
            if (!$member->profile || empty($member->profile->cv_url)) {
                $missingCvs[] = $member->first_name . ' ' . $member->last_name;
            }
        }

        // If anyone is missing a CV, block the application
        if (count($missingCvs) > 0) {
            return response()->json([
                'message' => 'Nemožno podať prihlášku. Nasledujúci členovia nemajú vo svojom profile nahraté CV: ' . implode(', ', $missingCvs)
            ], 422);
        }

        $hasExistingApplication = DB::table('applications')->where('team_id', $team->id)->exists();

        if ($hasExistingApplication) {
            return response()->json([
                'message' => 'Váš tím už podal prihlášku. Nemôžete podať viacero prihlášok súčasne.'
            ], 422);
        }

        try {
            // 4. Create the application without worrying about files
            $application = Application::create([
                'team_id'           => $team->id,
                'call_id'           => $validated['call_id'],
                'challenge_id'      => $validated['challenge_id'],
                'motivation_letter' => $validated['motivation_letter'] ?? null,
                'solution_proposal' => $validated['solution_proposal'] ?? null,
                'status'            => 'submitted',
                'submitted_at'      => now(),
            ]);

            // Notify the challenge's company managers about the new application.
            $challenge = CompanyChallenge::find($validated['challenge_id']);
            if ($challenge) {
                $managerIds = User::where('company_id', $challenge->company_id)
                    ->whereIn('company_role', ['owner', 'manager'])
                    ->pluck('id');

                Notification::notifyUsers(
                    $managerIds,
                    'application_received',
                    'New application',
                    "{$team->name} applied to your challenge \"{$challenge->title}\"."
                );
            }

            return response()->json([
                'message' => 'Prihláška bola úspešne podaná.',
                'application' => $application
            ], 201);

        } catch (\Exception $e) {
            Log::error('Chyba pri podávaní prihlášky: ' . $e->getMessage());
            return response()->json(['message' => 'Vyskytla sa chyba servera.'], 500);
        }
    }
    /**
     * Ensure the acting user may manage the company that owns this
     * application's challenge. NTI admins bypass via CompanyPolicy::before().
     */
    private function authorizeCompanyAction(Request $request, Application $application): ?JsonResponse
    {
        $company = $application->challenge?->company;

        if (!$company || $request->user()->cannot('manageChallenges', $company)) {
            return response()->json(['message' => 'Nemáte oprávnenie vykonať túto akciu.'], 403);
        }

        return null;
    }

    /**
     * COMPANY ACTION: Select a team for a challenge.
     */
    public function select(Request $request, Application $application): JsonResponse
    {
        if ($denied = $this->authorizeCompanyAction($request, $application)) {
            return $denied;
        }

        if ($application->status !== 'submitted') {
            return response()->json(['message' => 'Túto prihlášku už nie je možné upravovať.'], 422);
        }

        try {
            DB::transaction(function () use ($application) {
                // 1. Approve current application
                $application->update([
                    'status' => 'approved',
                    'decided_at' => now(),
                ]);
                if ($application->challenge) {
                    $application->challenge->update([
                        'team_id' => $application->team_id,
                        'status'  => 'assigned'
                    ]);
                }

                // 2. Automatically reject all other teams for THIS specific challenge
                Application::where('challenge_id', $application->challenge_id)
                    ->where('id', '!=', $application->id)
                    ->update([
                        'status' => 'rejected',
                        'decided_at' => now(),
                    ]);
                Milestone::create([
                    'application_id' => $application->id,
                    'title'          => 'Výber tímu a začiatok spolupráce',
                    'status'         => 'completed',
                    'due_date'       => now(),
                    'comment'        => 'Automaticky vytvorený míľnik po schválení tímu spoločnosťou.',
                ]);
            });

            // Notify the chosen team and the teams that were auto-rejected.
            $title = optional($application->challenge)->title ?? 'a challenge';

            $selectedMembers = DB::table('team_members')
                ->where('team_id', $application->team_id)
                ->pluck('user_id');
            Notification::notifyUsers(
                $selectedMembers,
                'team_selected',
                'Your team was selected! 🎉',
                "Your team was chosen for \"{$title}\"."
            );

            $rejectedTeamIds = Application::where('challenge_id', $application->challenge_id)
                ->where('id', '!=', $application->id)
                ->pluck('team_id');
            $rejectedMembers = DB::table('team_members')
                ->whereIn('team_id', $rejectedTeamIds)
                ->pluck('user_id');
            Notification::notifyUsers(
                $rejectedMembers,
                'team_rejected',
                'Application update',
                "Another team was selected for \"{$title}\"."
            );

            return response()->json(['message' => 'Team selected, others rejected.'], 200);
        } catch (\Exception $e) {
            Log::error('Selection error: ' . $e->getMessage());
            return response()->json(['message' => 'Server error.'], 500);
        }
    }

    /**
     * ADMIN ACTION: Assign university mentor to the project.
     */
    public function assignMentor(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();
        $isAdmin = in_array($user->account_type, ['nti_admin', 'superadmin'], true);

        if (!$isAdmin) {
            return response()->json(['message' => 'Nemáte oprávnenie pridat mentora.'], 403);
        }

        $validated = $request->validate(['mentor_id' => 'required|uuid|exists:users,id']);

        try {
            DB::transaction(function () use ($application, $validated) {
                Mentorship::create([
                    'application_id' => $application->id,
                    'mentor_id'      => $validated['mentor_id'],
                    'started_at'     => now(),
                    'notes'          => 'Záznam o mentoringu vytvorený po výbere tímu.',
                ]);
            });

            $title = optional($application->challenge)->title ?? 'a project';
            Notification::notifyUser(
                $validated['mentor_id'],
                'mentor_assigned',
                'New mentorship',
                "You've been assigned as mentor for \"{$title}\"."
            );

            return response()->json(['message' => 'Mentor assigned.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error.'], 500);
        }
    }

    /**
     * COMPANY ACTION: Assign Product Owner from company side.
     */
    public function assignPo(Request $request, Application $application): JsonResponse
    {
        if ($denied = $this->authorizeCompanyAction($request, $application)) {
            return $denied;
        }

        $validated = $request->validate(['product_owner_id' => 'required|uuid|exists:users,id']);

        $challenge = $application->challenge;
        if (!$challenge) {
            return response()->json(['message' => 'K prihláške nie je priradená žiadna výzva.'], 422);
        }

        // The Product Owner must be a member of the challenge's own company.
        $po = User::find($validated['product_owner_id']);
        if (!$po || $po->company_id !== $challenge->company_id) {
            return response()->json(['message' => 'Product Owner musí byť členom vašej spoločnosti.'], 422);
        }

        try {
            $challenge->update(['product_owner_id' => $validated['product_owner_id']]);
            return response()->json(['message' => 'Product Owner assigned.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error.'], 500);
        }
    }
    /**
     * COMPANY ACTION: Approve the final delivery of the project.
     * Closes the application, the challenge, and adds the final milestone.
     */
    public function approveDelivery(Request $request, Application $application): JsonResponse
    {
        if ($denied = $this->authorizeCompanyAction($request, $application)) {
            return $denied;
        }

        if ($application->status === 'archived') {
            return response()->json(['message' => 'Tento projekt už je uzavretý.'], 422);
        }

        try {
            DB::transaction(function () use ($application) {
                $application->update([
                    'status' => 'archived'
                ]);

                $challenge = CompanyChallenge::find($application->challenge_id);
                if ($challenge) {
                    $challenge->update([
                        'status' => 'closed'
                    ]);
                }

                Milestone::create([
                    'application_id' => $application->id,
                    'title'          => 'Finálne odovzdanie a schválenie projektu', // "Финальная сдача и одобрение проекта"
                    'status'         => 'completed',
                    'due_date'       => now(),
                    'comment'        => 'Spoločnosť schválila finálne riešenie. Projekt je úspešne ukončený.',
                ]);
            });

            return response()->json([
                'message' => 'Projekt bol úspešne schválený a uzavretý.',
                'application' => $application->fresh()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Approve delivery error: ' . $e->getMessage());
            return response()->json(['message' => 'Vyskytla sa chyba pri uzatváraní projektu.'], 500);
        }
    }
}
