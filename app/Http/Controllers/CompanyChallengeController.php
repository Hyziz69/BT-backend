<?php

namespace App\Http\Controllers;

use App\Models\CompanyChallenge;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CompanyChallengeController extends Controller
{
    /**
     * Возвращает список проектов (Challenges) внутри конкретного вызова.
     */
    public function index(Request $request): JsonResponse
    {

        $request->validate([
            'call_id' => 'required|uuid|exists:calls,id',
        ]);

        try {
            $challenges = CompanyChallenge::query()
                ->where('call_id', $request->query('call_id'))
                // ->with('company:id,name,logo_path')
                ->orderBy('created_at', 'desc')
                ->get([
                    'id',
                    'title',
                ]);

            return response()->json([
                'challenges' => $challenges
            ], 200);

        } catch (\Exception $e) {
            Log::error('Chyba pri načítaní projektov (Challenges): ' . $e->getMessage());
            return response()->json(['message' => 'Vyskytla sa chyba servera pri načítaní projektov.'], 500);
        }
    }

    /**
     * Детальная страница одного проекта (когда студент нажимает "Подробнее").
     */
    public function show(CompanyChallenge $challenge): JsonResponse
    {
        // $challenge->load('company');

        return response()->json([
            'challenge' => $challenge
        ], 200);
    }
}
