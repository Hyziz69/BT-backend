<?php

namespace App\Http\Controllers\Api\ProgramB;

use App\Http\Controllers\Controller;
use App\Models\CompanyChallenge;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CompanyChallengeController extends Controller
{
    /**
     * PUBLIC VIEW (Students): List challenges for a specific call.
     */
    public function index(Request $request): JsonResponse
    {
//        $request->validate([
//            'call_id' => 'required|uuid|exists:calls,id',
//        ]);

        try {
            $challenges = CompanyChallenge::query()
//                ->where('call_id', $request->query('call_id'))
                // CRITICAL: Students should only see published or active challenges, not drafts!
                ->whereIn('status', ['published', 'in_pairing'])
                ->orderBy('created_at', 'desc')
                ->get([
                    'id',
                    'title',
                    'status' // helpful for frontend
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
     * PUBLIC VIEW (Students): Show details of a single challenge.
     */
    public function show(CompanyChallenge $challenge): JsonResponse
    {
        // TODO: Ensure the challenge is 'published' before showing details to a student

        return response()->json([
            'challenge' => $challenge
        ], 200);
    }

    /**
     * COMPANY ACTION: Create a new challenge (draft).
     */
    public function store(Request $request): JsonResponse
    {
        // Validate incoming payload. Product owner is probably a user from the company
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'technical_spec'   => 'required|string',
            'call_id'          => 'nullable',
            'company_id'       => 'nullable',
            'budget'           => 'nullable|numeric|min:0',
//            'product_owner_id' => 'required|uuid|exists:users,id', // Make sure users table uses UUIDs
            // TODO: later we might need to automatically append 'company_id' based on the logged-in user
        ]);

        try {
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
     * COMPANY ACTION: Update a challenge.
     */
    public function update(Request $request, CompanyChallenge $challenge): JsonResponse
    {
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
        // Strictly validate against the workflow states defined in the specs
        $validated = $request->validate([
            'status' => 'required|in:draft,published,in_pairing,assigned,closed'
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
