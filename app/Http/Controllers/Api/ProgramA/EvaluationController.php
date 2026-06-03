<?php

namespace App\Http\Controllers\Api\ProgramA;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Evaluation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    public function index(Request $request, Application $application): JsonResponse
    {
        $this->authorizeAccess($request->user(), $application);

        $evaluations = $application->evaluations()
            ->with('evaluator:id,first_name,last_name,email')
            ->get();

        return response()->json(['data' => $evaluations]);
    }

    public function store(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->account_type, ['nti_admin', 'superadmin', 'evaluator'])) {
            return response()->json(['message' => 'Only evaluators can submit evaluations.'], 403);
        }

        $validated = $request->validate([
            'score'    => ['required', 'integer', 'min:0', 'max:100'],
            'comment'  => ['nullable', 'string', 'max:2000'],
            'criteria' => ['nullable', 'string', 'max:255'],
        ]);

        $existing = $application->evaluations()
            ->where('evaluator_id', $user->id)
            ->first();

        if ($existing) {
            $existing->update($validated);
            return response()->json([
                'message' => 'Evaluation updated.',
                'data'    => $existing->load('evaluator:id,first_name,last_name,email'),
            ]);
        }

        $evaluation = $application->evaluations()->create([
            'evaluator_id' => $user->id,
            'score'        => $validated['score'],
            'comment'      => $validated['comment'] ?? null,
            'criteria'     => $validated['criteria'] ?? null,
        ]);

        return response()->json([
            'message' => 'Evaluation submitted.',
            'data'    => $evaluation->load('evaluator:id,first_name,last_name,email'),
        ], 201);
    }

    public function destroy(Request $request, Application $application, Evaluation $evaluation): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->account_type, ['nti_admin', 'superadmin']) && $evaluation->evaluator_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $evaluation->delete();

        return response()->json(['message' => 'Evaluation deleted.']);
    }

    private function authorizeAccess($user, Application $application): void
    {
        if (in_array($user->account_type, ['nti_admin', 'superadmin', 'evaluator'])) {
            return;
        }

        $isMember = $application->team->members()->where('user_id', $user->id)->exists();
        if (!$isMember) {
            abort(403, 'Access denied.');
        }
    }
}