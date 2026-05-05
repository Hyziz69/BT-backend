<?php

namespace App\Http\Controllers;

use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ProgramController extends Controller
{
    /**
     * Возвращает список активных программ для первого шага выбора.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $programs = Program::where('is_active', 1)
                ->orderBy('created_at', 'desc')
                ->get(['type','name', 'description']);

            return response()->json([
                'programs' => $programs
            ], 200);

        } catch (\Exception $e) {
            Log::error('Chyba pri načítaní programov: ' . $e->getMessage());
            return response()->json(['message' => 'Vyskytla sa chyba servera pri načítaní programov.'], 500);
        }
    }
}
