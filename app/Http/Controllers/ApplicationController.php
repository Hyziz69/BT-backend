<?php

namespace App\Http\Controllers;

use App\Models\Application;
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
        $validated = $request->validate([
            'user_id'           => 'required|uuid',
            'team_id'           => 'required|uuid|exists:teams,id',
            'call_id'           => 'required|uuid|exists:calls,id',
            'challenge_id'      => 'required|uuid',
            'motivation_letter' => 'required|string',
            'solution_proposal' => 'nullable|string',
        ]);

        $user = User::find($validated['user_id']);
        $team = Team::find($validated['team_id']);

        if (!$user) {
            return response()->json(['message' => 'Používateľ nenájdený.'], 404);
        }

        if ($team->leader_id !== $user->id) {
            return response()->json(['message' => 'Iba líder tímu môže podať prihlášku.'], 403);
        }

        $hasExistingApplication = DB::table('applications')->where('team_id', $team->id)->exists();

        if ($hasExistingApplication) {
            return response()->json([
                'message' => 'Váš tím už podal prihlášku. Nemôžete podať viacero prihlášok súčasne.'
            ], 422);
        }

        try {
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
}
