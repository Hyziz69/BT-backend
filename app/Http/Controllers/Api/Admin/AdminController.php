<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AccountApprovedMail;
use App\Mail\AccountRejectedMail;
use App\Models\Application;
use App\Models\Call;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'users_count' => User::count(),
            'active_users_count' => User::where('status', 'active')->count(),
            'pending_users_count' => User::where('status', 'pending')->count(),
            'rejected_users_count' => User::where('status', 'suspended')->count(),

            'students_count' => User::where('account_type', 'student')->count(),
            'admins_count' => User::where('account_type', 'nti_admin')->count(),
            'mentors_count' => User::where('account_type', 'mentor')->count(),

            'total_programs' => Program::count(),
            'active_programs' => Program::where('is_active', true)->count(),

            'total_calls' => Call::count(),
            'open_calls' => Call::where('status', 'open')->count(),
            'closed_calls' => Call::where('status', 'closed')->count(),
            'draft_calls' => Call::where('status', 'draft')->count(),

            'total_applications' => Application::count(),
            'approved_applications' => Application::where('status', 'approved')->count(),
            'rejected_applications' => Application::where('status', 'rejected')->count(),
            'pending_applications' => Application::whereNotIn('status', [
                'approved',
                'rejected',
                'completed',
                'archived',
            ])->count(),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $query = User::query()->select([
            'id',
            'first_name',
            'last_name',
            'email',
            'account_type',
            'status',
            'gdpr_consent',
            'created_at',
        ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('account_type')) {
            $query->where('account_type', $request->account_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->orderBy('created_at', 'desc')->get();

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
                    'mentor',
                    'company_contact',
                    'editor',
                    'nti_admin',
                    'superadmin',
                ]),
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    'active',
                    'pending',
                    'suspended',
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

    public function approveUser(User $user): JsonResponse
    {
        $user->update([
            'status' => 'active',
        ]);

        $mailSent = true;

        try {
            Mail::to($user->email)->send(new AccountApprovedMail($user));
        } catch (\Throwable $e) {
            $mailSent = false;

            Log::error('AccountApprovedMail failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => $mailSent
                ? 'User approved successfully.'
                : 'User approved successfully, but email was not sent.',
            'mail_sent' => $mailSent,
            'user' => $user->fresh()->only([
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

    public function rejectUser(User $user): JsonResponse
    {
        $user->update([
            'status' => 'suspended',
        ]);

        $mailSent = true;

        try {
            Mail::to($user->email)->send(new AccountRejectedMail($user));
        } catch (\Throwable $e) {
            $mailSent = false;

            Log::error('AccountRejectedMail failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => $mailSent
                ? 'User rejected successfully.'
                : 'User rejected successfully, but email was not sent.',
            'mail_sent' => $mailSent,
            'user' => $user->fresh()->only([
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