<?php

namespace App\Http\Controllers\Api\ProgramB;

use App\Http\Controllers\Controller;
use App\Models\Call;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CallController extends Controller
{
    /**
     * Возвращает список активных вызовов (Calls) для выбранной программы (Шаг 2).
     */
    public function index(): JsonResponse
    {
        $calls = Call::with('program')
            ->whereHas('program', fn ($q) => $q->where('type', 'program_b'))
            ->where('status', 'open')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($call) => [
                'id'                  => $call->id,
                'title'               => $call->title,
                'description'         => $call->description,
                'status'              => $call->status,
                'opens_at'            => $call->opens_at,
                'closes_at'           => $call->closes_at,
                'evaluation_criteria' => $call->evaluation_criteria ?? [],
                'program'             => [
                    'id'   => $call->program->id,
                    'type' => $call->program->type,
                    'name' => $call->program->name,
                ],
            ]);

        return response()->json(['data' => $calls]);
    }
}
