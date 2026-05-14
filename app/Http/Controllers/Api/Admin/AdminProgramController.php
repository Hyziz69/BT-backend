<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminProgramController extends Controller
{
    public function index(): JsonResponse
    {
        $programs = Program::withCount('calls')
            ->orderBy('name')
            ->get();

        return response()->json($programs);
    }

    public function show(Program $program): JsonResponse
    {
        return response()->json(
            $program->load('calls')
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['program_a', 'program_b'])],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'min_team_size' => ['required', 'integer', 'min:1'],
            'max_team_size' => ['required', 'integer', 'gte:min_team_size'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $program = Program::create($validated);

        return response()->json([
            'message' => 'Program created successfully.',
            'data' => $program,
        ], 201);
    }

    public function update(Request $request, Program $program): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['sometimes', 'required', Rule::in(['program_a', 'program_b'])],
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'min_team_size' => ['sometimes', 'required', 'integer', 'min:1'],
            'max_team_size' => ['sometimes', 'required', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (
            isset($validated['min_team_size'], $validated['max_team_size']) &&
            $validated['max_team_size'] < $validated['min_team_size']
        ) {
            return response()->json([
                'message' => 'max_team_size must be greater than or equal to min_team_size.',
            ], 422);
        }

        $program->update($validated);

        return response()->json([
            'message' => 'Program updated successfully.',
            'data' => $program->fresh(),
        ]);
    }
}