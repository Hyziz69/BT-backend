<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Call;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCallController extends Controller
{
    public function index(): JsonResponse
    {
        $calls = Call::with(['program', 'createdBy'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($calls);
    }

    public function show(Call $call): JsonResponse
    {
        return response()->json(
            $call->load(['program', 'createdBy', 'applications'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'program_id' => ['required', 'uuid', 'exists:programs,id'],
            'title' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string'],
            'evaluation_criteria' => ['nullable', 'array'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
        ]);

        $call = Call::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'status' => 'draft',
        ]);

        return response()->json([
            'message' => 'Call created successfully.',
            'data' => $call->load(['program', 'createdBy']),
        ], 201);
    }

    public function update(Request $request, Call $call): JsonResponse
    {
        $validated = $request->validate([
            'program_id' => ['sometimes', 'required', 'uuid', 'exists:programs,id'],
            'title' => ['sometimes', 'required', 'string', 'max:300'],
            'description' => ['nullable', 'string'],
            'evaluation_criteria' => ['nullable', 'array'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'status' => ['sometimes', 'required', Rule::in(['draft', 'open', 'evaluating', 'closed'])],
        ]);

        $call->update($validated);

        return response()->json([
            'message' => 'Call updated successfully.',
            'data' => $call->load(['program', 'createdBy']),
        ]);
    }

    public function open(Call $call): JsonResponse
    {
        $call->update([
            'status' => 'open',
            'opens_at' => $call->opens_at ?? now(),
        ]);

        return response()->json([
            'message' => 'Call opened successfully.',
            'data' => $call->fresh(['program', 'createdBy']),
        ]);
    }

    public function close(Call $call): JsonResponse
    {
        $call->update([
            'status' => 'closed',
            'closes_at' => $call->closes_at ?? now(),
        ]);

        return response()->json([
            'message' => 'Call closed successfully.',
            'data' => $call->fresh(['program', 'createdBy']),
        ]);
    }
}