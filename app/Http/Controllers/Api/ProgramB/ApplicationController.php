<?php

namespace App\Http\Controllers\Api\ProgramB;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\CompanyChallenge;
use App\Models\Mentorship;
use App\Models\Milestone;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApplicationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // 1. We no longer expect files here, just standard JSON
        $validated = $request->validate([
            'user_id'           => 'required|uuid',
            'team_id'           => 'required|uuid|exists:teams,id',
            'call_id'           => 'required|uuid|exists:calls,id',
            'challenge_id'      => 'required|uuid',
            'motivation_letter' => 'required|string',
            'solution_proposal' => 'nullable|string',
        ]);

        $user = User::find($validated['user_id']);

        // 2. Load the team along with its members and their profiles!
        // This is crucial for checking the CVs in one go.
        $team = Team::with('members.profile')->find($validated['team_id']);

        if (!$user) {
            return response()->json(['message' => 'Používateľ nenájdený.'], 404);
        }

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
                'motivation_letter' => $validated['motivation_letter'],
                'solution_proposal' => $validated['solution_proposal'] ?? null,
                'status'            => 'submitted',
                'submitted_at'      => now(),
            ]);

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
     * ADMIN/COMPANY ACTION: Select a team for a challenge.
     */
    public function select(Request $request, Application $application): JsonResponse
    {
        // TODO: Add NTI Admin or Company Representative check

        try {
            DB::transaction(function () use ($application) {
                // 1. Approve current application
                $application->update([
                    'status' => 'approved',
                    'decided_at' => now(),
                ]);
                $challenge = CompanyChallenge::find($application->challenge_id);
                if ($challenge) {
                    $challenge->update([
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
        $validated = $request->validate(['product_owner_id' => 'required|uuid|exists:users,id']);

        try {
            $application->update(['product_owner_id' => $validated['product_owner_id']]);
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
        if ($application->status === 'archived') {
            return response()->json(['message' => 'Tento projekt už je uzavretý.'], 422);
        }

        try {
            DB::transaction(function () use ($application) {
                $application->update([
                    'status' => 'archived'
                ]);

                $challenge = \App\Models\CompanyChallenge::find($application->challenge_id);
                if ($challenge) {
                    $challenge->update([
                        'status' => 'closed'
                    ]);
                }

                \App\Models\Milestone::create([
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
            \Illuminate\Support\Facades\Log::error('Approve delivery error: ' . $e->getMessage());
            return response()->json(['message' => 'Vyskytla sa chyba pri uzatváraní projektu.'], 500);
        }
    }
}
