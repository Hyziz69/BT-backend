<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Call;
use App\Models\User;

class AdminController extends Controller
{
    public function dashboard()
    {
        return response()->json([
            'users_count' => User::count(),
            'nti_admins_count' => User::where('account_type', 'nti_admin')->count(),
            'students_count' => User::where('account_type', 'student')->count(),
            'mentors_count' => User::where('account_type', 'mentor')->count(),
            'calls_count' => Call::count(),
            'applications_count' => Application::count(),
        ]);
    }

    public function users()
    {
        return response()->json(
            User::query()
                ->select('id', 'first_name', 'last_name', 'email', 'account_type', 'status', 'created_at')
                ->latest()
                ->get()
        );
    }

    public function showUser(User $user)
    {
        return response()->json([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'account_type' => $user->account_type,
            'status' => $user->status,
            'company_id' => $user->company_id,
            'created_at' => $user->created_at,
        ]);
    }

    public function applications()
    {
        return response()->json(
            Application::query()
                ->latest()
                ->get()
        );
    }

    public function showApplication(Application $application)
    {
        return response()->json($application);
    }
}