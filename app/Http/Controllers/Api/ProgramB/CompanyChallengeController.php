<?php

namespace App\Http\Controllers\Api\ProgramB;

use App\Http\Controllers\Controller;
use App\Models\CompanyChallenge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CompanyChallengeController extends Controller
{
    /**
     * Role-aware listing:
     *  - Company managers/owners see ALL of their own company's challenges
     *    (drafts included) with a count of pending candidate teams.
     *  - Students / public see only published or matching challenges.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            if ($user->belongsToCompany() && $user->isCompanyManager()) {
                $challenges = CompanyChallenge::query()
                    ->where('company_id', $user->company_id)
                    ->withCount(['applications as candidates_count' => function ($q) {
                        $q->whereNotIn('status', ['rejected', 'archived']);
                    }])
                    ->orderBy('created_at', 'desc')
                    ->get();

                return response()->json([
                    'challenges' => $challenges
                ], 200);
            }

            // CRITICAL: Students should only see published or active challenges, not drafts!
            $challenges = CompanyChallenge::query()
                ->whereIn('status', ['published', 'matching'])
                ->orderBy('created_at', 'desc')
                ->get([
                    'id',
                    'title',
                    'status',
                    'company_id',
                    'budget',
                ]);

            return response()->json([
                'challenges' => $challenges
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to load challenges: ' . $e->getMessage());
            return response()->json(['message' => 'Server error occurred while loading challenges.'], 500);
        }
    }

    /**
     * COMPANY ACTION: List the candidate teams (applications) for a challenge.
     */
    public function applications(Request $request, CompanyChallenge $challenge): JsonResponse
    {
        if ($denied = $this->authorizeChallenge($request, $challenge)) {
            return $denied;
        }

        $applications = $challenge->applications()
            ->with(['team.members.profile'])
            ->orderByRaw("CASE status WHEN 'submitted' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'applications' => $applications
        ], 200);
    }

    /**
     * Show details of a single challenge (role-aware).
     *  - Owning-company managers get full data and can_manage = true.
     *  - Drafts are hidden from everyone except those managers.
     */
    public function show(Request $request, CompanyChallenge $challenge): JsonResponse
    {
        $user = $request->user();
        $canManage = (bool) ($challenge->company && $user->can('manageChallenges', $challenge->company));

        if ($challenge->status === 'draft' && !$canManage) {
            return response()->json(['message' => 'Výzva nie je dostupná.'], 403);
        }

        $challenge->load([
            'company:id,name',
            'productOwner:id,first_name,last_name',
            'call:id,title',
            'selectedTeam:id,name',
        ]);
        $challenge->loadCount(['applications as candidates_count' => function ($q) {
            $q->whereNotIn('status', ['rejected', 'archived']);
        }]);

        $payload = [
            'challenge' => $challenge,
            'can_manage' => $canManage,
        ];

        if ($canManage) {
            $isAdmin = in_array($user->account_type, ['nti_admin', 'superadmin'], true);

            // Eligible Product Owners = members of the challenge's own company.
            $payload['po_candidates'] = User::where('company_id', $challenge->company_id)
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name']);

            // The already-selected team's application (if any), with its mentor.
            $approved = $challenge->applications()
                ->where('status', 'approved')
                ->with(['mentorships.mentor:id,first_name,last_name'])
                ->first();

            if ($approved) {
                $payload['assigned_application_id'] = $approved->id;
                $mentor = optional($approved->mentorships->first())->mentor;
                $payload['current_mentor'] = $mentor
                    ? ['id' => $mentor->id, 'name' => trim($mentor->first_name . ' ' . $mentor->last_name)]
                    : null;
            }

            // Mentor assignment is an NTI-admin action.
            if ($isAdmin) {
                $payload['mentor_candidates'] = User::where('account_type', 'mentor')
                    ->orderBy('first_name')
                    ->get(['id', 'first_name', 'last_name']);
            }
        }

        return response()->json($payload, 200);
    }

    /**
     * COMPANY ACTION: Create a new challenge (draft).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // The challenge is always created on behalf of the user's own company.
        if (!$user->belongsToCompany()) {
            return response()->json(['message' => 'Nie ste priradený k žiadnej spoločnosti.'], 422);
        }

        $company = $user->company;

        if ($user->cannot('manageChallenges', $company)) {
            return response()->json(['message' => 'Nemáte oprávnenie vytvárať výzvy pre túto spoločnosť.'], 403);
        }

        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'technical_spec'   => 'required|string',
            'call_id'          => 'nullable|uuid|exists:calls,id',
            'budget'           => 'nullable|numeric|min:0',
            'product_owner_id' => 'nullable|uuid|exists:users,id',
        ]);

        try {
            // company_id comes from the authenticated user, never from the client.
            $validated['company_id'] = $company->id;
            // New challenges should always start as drafts
            $validated['status'] = 'draft';

            $challenge = CompanyChallenge::create($validated);

            return response()->json([
                'message' => 'CompanyChallenge created successfully.',
                'challenge' => $challenge
            ], 201);

        } catch (\Exception $e) {
            // Log for debugging
            Log::error('Failed to create challenge: ' . $e->getMessage());
            return response()->json(['message' => 'Server error occurred.'], 500);
        }
    }

    /**
     * Ensure the acting user may manage the given challenge's company.
     */
    private function authorizeChallenge(Request $request, CompanyChallenge $challenge): ?JsonResponse
    {
        $company = $challenge->company;

        if (!$company || $request->user()->cannot('manageChallenges', $company)) {
            return response()->json(['message' => 'Nemáte oprávnenie upravovať túto výzvu.'], 403);
        }

        return null;
    }

    /**
     * COMPANY ACTION: Update a challenge.
     */
    public function update(Request $request, CompanyChallenge $challenge): JsonResponse
    {
        if ($denied = $this->authorizeChallenge($request, $challenge)) {
            return $denied;
        }

        // Using 'sometimes' so frontend can patch only the edited fields
        $validated = $request->validate([
            'title'            => 'sometimes|required|string|max:255',
            'technical_spec'   => 'sometimes|required|string',
            'budget'           => 'nullable|numeric|min:0',
            'product_owner_id' => 'sometimes|required|uuid|exists:users,id',
        ]);

        try {
            $challenge->update($validated);

            return response()->json([
                'message' => 'CompanyChallenge updated.',
                'challenge' => $challenge->fresh()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to update challenge: ' . $e->getMessage());
            return response()->json(['message' => 'Server error occurred.'], 500);
        }
    }

    /**
     * COMPANY ACTION: Update the status of a challenge.
     */
    public function updateStatus(Request $request, CompanyChallenge $challenge): JsonResponse
    {
        if ($denied = $this->authorizeChallenge($request, $challenge)) {
            return $denied;
        }

        // Strictly validate against the workflow states defined in the migration enum
        $validated = $request->validate([
            'status' => 'required|in:draft,published,matching,assigned,in_progress,closed'
        ]);

        try {
            $challenge->update(['status' => $validated['status']]);

            return response()->json([
                'message' => "CompanyChallenge status updated to {$validated['status']}.",
                'challenge' => $challenge->fresh()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to update challenge status: ' . $e->getMessage());
            return response()->json(['message' => 'Server error occurred.'], 500);
        }
    }
}
