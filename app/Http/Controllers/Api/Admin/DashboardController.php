<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Call;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'total_applications' => Application::count(),
            'approved_applications' => Application::where('status', 'approved')->count(),
            'rejected_applications' => Application::where('status', 'rejected')->count(),
            'pending_applications' => Application::whereNotIn('status', [
                'approved',
                'rejected',
                'completed',
                'archived',
            ])->count(),

            'total_calls' => Call::count(),
            'open_calls' => Call::where('status', 'open')->count(),
            'closed_calls' => Call::where('status', 'closed')->count(),
            'draft_calls' => Call::where('status', 'draft')->count(),

            'total_programs' => Program::count(),
            'active_programs' => Program::where('is_active', true)->count(),

            'total_mentors' => User::where('account_type', 'mentor')->count(),
            'total_users' => User::count(),

            'pending_users' => User::where('status', 'pending')->count(),
            'active_users' => User::where('status', 'active')->count(),
            'rejected_users' => User::where('status', 'suspended')->count(),
        ]);
    }
}