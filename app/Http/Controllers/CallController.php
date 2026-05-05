<?php

namespace App\Http\Controllers;

use App\Models\Call;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CallController extends Controller
{
    /**
     * Возвращает список активных вызовов (Calls) для выбранной программы (Шаг 2).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'program_id' => 'required|uuid|exists:programs,id',
        ]);

        try {
            $calls = Call::query()
                ->where('program_id', $request->query('program_id'))

                ->where('status', 'open')
                ->whereDate('opens_at', '<=', now())
                ->whereDate('closes_at', '>=', now())
                ->orderBy('closes_at', 'asc')
                ->get([
                    'id',
                    'title',
                    'description',
                    'opens_at',
                    'closes_at'
                ]);

            return response()->json([
                'calls' => $calls
            ], 200);

        } catch (\Exception $e) {
            Log::error('Chyba pri načítaní výziev (Calls): ' . $e->getMessage());
            return response()->json(['message' => 'Vyskytla sa chyba servera pri načítaní výziev.'], 500);
        }
    }
}
