<?php

namespace App\Http\Controllers\Api\ProgramA;

use App\Http\Controllers\Controller;
use App\Models\Call;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function index(): JsonResponse
    {
        $calls = Call::with('program')
            ->whereHas('program', fn ($q) => $q->where('type', 'program_a'))
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

    public function show(Call $call): JsonResponse
    {
        if ($call->program->type !== 'program_a') {
            return response()->json(['message' => 'Call not found.'], 404);
        }

        return response()->json([
            'data' => [
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
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'program_id'           => ['required', 'uuid', 'exists:programs,id'],
            'title'                => ['required', 'string', 'max:300'],
            'description'          => ['nullable', 'string'],
            'evaluation_criteria'  => ['nullable', 'array'],
            'opens_at'             => ['nullable', 'date'],
            'closes_at'            => ['nullable', 'date', 'after:opens_at'],
        ]);

        $call = Call::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'status'     => 'open',
        ]);

        return response()->json([
            'message' => 'Call created.',
            'data'    => $call->load('program'),
        ], 201);
    }

    public function update(Request $request, Call $call): JsonResponse
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'title'       => ['sometimes', 'string', 'max:300'],
            'description' => ['nullable', 'string'],
            'status'      => ['sometimes', 'in:draft,open,evaluating,closed'],
            'opens_at'    => ['nullable', 'date'],
            'closes_at'   => ['nullable', 'date'],
        ]);

        $call->update($validated);

        return response()->json([
            'message' => 'Call updated.',
            'data'    => $call->load('program'),
        ]);
    }

    private function ensureAdmin($user): void
    {
        if (!in_array($user->account_type, ['nti_admin', 'superadmin'])) {
            abort(403, 'Admin access required.');
        }
    }
}