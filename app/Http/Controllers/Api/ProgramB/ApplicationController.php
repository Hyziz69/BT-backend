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
    private const ALLOWED_TRANSITIONS = [
        'draft'              => ['submitted'],
        'submitted'          => ['formally_verified', 'pending_supplement', 'rejected'],
        'pending_supplement' => ['submitted'],
        'formally_verified'  => ['in_evaluation', 'approved', 'rejected'],
        'in_evaluation'      => ['approved', 'rejected'],
        'approved'           => ['onboarding', 'archived'],
        'onboarding'         => ['active', 'paused', 'archived'],
        'active'             => ['completed', 'paused', 'archived'],
        'paused'             => ['active', 'archived'],
        'completed'          => ['archived'],
        'rejected'           => ['archived'],
        'archived'           => []
    ];
    /**
     * Получить список всех заявок.
     */
    public function index(Request $request): JsonResponse
    {
        // Загружаем заявки вместе со связанными данными для удобства фронтенда
        $applications = Application::with(['team', 'challenge', 'call'])->get();

        return response()->json($applications, 200);
    }

    /**
     * Получить детальную информацию об одной заявке.
     */
    public function show(Application $application): JsonResponse
    {
        // Подгружаем связанные данные для конкретной заявки
        $application->load(['team.members.profile', 'challenge', 'call', 'mentorships.mentor',]);
        $application->setAttribute(
            'available_transitions',
            self::ALLOWED_TRANSITIONS[$application->status] ?? []
        );

        return response()->json($application, 200);
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
            if (!$member->profile || empty($member->profile->cv_path)) {
                $missingCvs[] = $member->first_name . ' ' . $member->last_name;
            }
        }

//         If anyone is missing a CV, block the application
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
                'status'            => 'draft',
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
        $user = $request->user();
        $isAdmin = $user->account_type === 'nti_admin';

        if (!$isAdmin) {
            return response()->json(['message' => 'Nemáte oprávnenie schvalit ziadost.'], 403);
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
        $isAdmin = $user->account_type === 'nti_admin';

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
        $user = $request->user();
        $isAdmin = $user->account_type === 'nti_admin';

        if (!$isAdmin) {
            return response()->json(['message' => 'Nemáte oprávnenie pridat product ownera.'], 403);
        }

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
        $user = $request->user();
        $isAdmin = $user->account_type === 'nti_admin';
        if (!$isAdmin) {
            return response()->json(['message' => 'Nemáte oprávnenie schváliť tento projekt.'], 403);
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
    public function transition(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'status'         => 'required|string|in:draft,submitted,formally_verified,in_evaluation,pending_supplement,approved,rejected,onboarding,active,paused,completed,archived',
            'decision_notes' => 'nullable|string',
            'score'          => 'nullable|numeric|min:0|max:100',
        ]);

        $newStatus = $validated['status'];
        $currentStatus = $application->status;
        $allowed = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];
        $isAdmin = in_array($user->account_type, ['nti_admin', 'superadmin']);

        if (!in_array($newStatus, $allowed)) {
            return response()->json([
                'message' => "Neregulárny prechod zo stavu '{$currentStatus}' do stavu '{$newStatus}'."
            ], 422);
        }

        if ($newStatus === 'submitted') {
            if (!$isAdmin && $application->team->leader_id !== $user->id) {
                return response()->json(['message' => 'Iba líder tímu môže podať prihlášku.'], 403);
            }

            $missingCvs = [];
            $application->loadMissing('team.members.profile');

            foreach ($application->team->members as $member) {
                if (!$member->profile || empty($member->profile->cv_path)) {
                    $missingCvs[] = $member->first_name . ' ' . $member->last_name;
                }
            }

            if (!empty($missingCvs)) {
                return response()->json([
                    'message' => 'Nemožno podať prihlášku. Nasledujúci členovia nemajú vo svojom profile nahraté CV: ' . implode(', ', $missingCvs)
                ], 422);
            }

            $application->submitted_at = now();
        } else {
            if (!$isAdmin) {
                return response()->json(['message' => 'Nedostatočné oprávnenia pre tento prechod stavu.'], 403);
            }

            if (array_key_exists('decision_notes', $validated)) {
                $application->decision_notes = $validated['decision_notes'];
            }

            if (in_array($newStatus, ['approved', 'rejected'])) {
                $application->score = $validated['score'] ?? null;
                $application->decided_at = now();
            }
        }

        $application->status = $newStatus;
        $application->save();

        $application->load([
            'team.members.profile',
            'call.program',
            'challenge',
            'milestones',
            'mentorships.mentor'
        ]);

        $application->setAttribute(
            'available_transitions',
            self::ALLOWED_TRANSITIONS[$application->status] ?? []
        );

        return response()->json($application, 200);
    }
}
