<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Call;
use App\Models\Program;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $reviewStatuses = [
            'submitted',
            'formally_verified',
            'in_evaluation',
            'pending_supplement',
        ];

        $activeProjectStatuses = [
            'approved',
            'onboarding',
            'active',
            'paused',
        ];

        $usersCount = User::count();
        $activeUsersCount = User::where('status', 'active')->count();
        $pendingUsersCount = User::where('status', 'pending')->count();
        $rejectedUsersCount = User::where('status', 'suspended')->count();

        $mentorsCount = User::where('account_type', 'mentor')->count();
        $activeMentorsCount = User::where('account_type', 'mentor')
            ->where('status', 'active')
            ->count();

        $applicationsWaitingCount = Application::whereIn('status', $reviewStatuses)->count();

        $activeProjectsCount = Application::whereIn('status', $activeProjectStatuses)->count();

        $teamsWithActiveProjectsCount = Application::whereIn('status', $activeProjectStatuses)
            ->whereNotNull('team_id')
            ->distinct('team_id')
            ->count('team_id');

        return response()->json([
            'users_count' => $usersCount,
            'active_users_count' => $activeUsersCount,
            'pending_users_count' => $pendingUsersCount,
            'rejected_users_count' => $rejectedUsersCount,
            'students_count' => User::where('account_type', 'student')->count(),
            'admins_count' => User::whereIn('account_type', ['nti_admin', 'superadmin'])->count(),

            'mentors_count' => $mentorsCount,
            'active_mentors_count' => $activeMentorsCount,
            'available_mentors_count' => $activeMentorsCount,

            'teams_count' => Team::count(),
            'teams_with_active_projects_count' => $teamsWithActiveProjectsCount,

            'total_programs' => Program::count(),
            'active_programs' => Program::where('is_active', true)->count(),

            'total_calls' => Call::count(),
            'open_calls' => Call::where('status', 'open')->count(),
            'evaluating_calls' => Call::where('status', 'evaluating')->count(),
            'closed_calls' => Call::where('status', 'closed')->count(),
            'draft_calls' => Call::where('status', 'draft')->count(),

            'total_applications' => Application::count(),
            'applications_waiting_count' => $applicationsWaitingCount,
            'approved_applications' => Application::where('status', 'approved')->count(),
            'active_projects_count' => $activeProjectsCount,
            'completed_applications' => Application::whereIn('status', ['completed', 'archived'])->count(),
            'rejected_applications' => Application::where('status', 'rejected')->count(),
            'pending_applications' => $applicationsWaitingCount,

            'total_users' => $usersCount,
            'pending_users' => $pendingUsersCount,
            'active_users' => $activeUsersCount,
            'rejected_users' => $rejectedUsersCount,
            'total_mentors' => $mentorsCount,
        ]);
    }
}