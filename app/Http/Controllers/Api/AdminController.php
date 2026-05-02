<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'users_count' => User::count(),
            'active_users_count' => User::where('status', 'active')->count(),
            'students_count' => User::where('account_type', 'student')->count(),
            'admins_count' => User::where('account_type', 'nti_admin')->count(),
        ]);
    }

    public function users(): JsonResponse
    {
        $users = User::query()
            ->select([
                'id',
                'first_name',
                'last_name',
                'email',
                'account_type',
                'status',
                'gdpr_consent',
                'created_at',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($users);
    }

    public function showUser(User $user): JsonResponse
    {
        return response()->json($user);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'account_type' => [
                'sometimes',
                'required',
                Rule::in([
                    'student',
                    'firm',
                    'mentor',
                    'committee',
                    'editor',
                    'firm_admin',
                    'nti_admin',
                    'super_admin',
                ]),
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    'active',
                    'pending',
                    'suspended',
                    'blocked',
                ]),
            ],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $user->only([
                'id',
                'first_name',
                'last_name',
                'email',
                'account_type',
                'status',
                'gdpr_consent',
                'created_at',
            ]),
        ]);
    }
}