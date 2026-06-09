<?php

use App\Http\Controllers\Api\Admin\AdminApplicationController;
use App\Http\Controllers\Api\Admin\AdminAuditEventController;
use App\Http\Controllers\Api\Admin\AdminCallController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\AdminProgramController;
use App\Http\Controllers\Api\Mentor\MentorshipController as MentorMentorshipController;
use App\Http\Controllers\Api\Admin\AdminReportController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProgramA\TeamInvitationController;
use App\Http\Controllers\Api\ProgramB\CompanyMemberController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/reset', [AuthController::class, 'forgotPassword']);
Route::post('/password/reset/confirm', [AuthController::class, 'resetPassword']);

Route::get('/program-a/invitations/{token}', [TeamInvitationController::class, 'preview']);
Route::get('/program-b/companies/invitations/{token}', [CompanyMemberController::class, 'preview']);

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/users', [UserController::class, 'index']);

    // Mentor workspace (the mentor's own mentorships)
    Route::prefix('mentor')->group(function () {
        Route::get('/mentorships', [MentorMentorshipController::class, 'index']);
        Route::get('/mentorships/{mentorship}', [MentorMentorshipController::class, 'show']);
        Route::post('/mentorships/{mentorship}/consultations', [MentorMentorshipController::class, 'storeConsultation']);
    });

    // Notifications (bell)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/profile/overview', [ProfileController::class, 'overview']);
    Route::patch('/profile/details', [ProfileController::class, 'updateDetails']);
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar']);
    Route::post('/profile/cv', [ProfileController::class, 'uploadCv']);
    Route::delete('/profile/cv', [ProfileController::class, 'deleteCv']);
    Route::patch('/profile/student-profile', [ProfileController::class, 'updateStudentProfile']);
    Route::patch('/profile/password', [ProfileController::class, 'changePassword']);

    Route::get('/users/{user}/profile', [ProfileController::class, 'publicProfile']);
    Route::get('/users/{user}/profile-card', [ProfileController::class, 'profileCard']);

    Route::patch('/user/password', [ProfileController::class, 'changePassword']);

    Route::post('/program-a/invitations/accept', [TeamInvitationController::class, 'accept']);
    Route::post('/program-b/companies/invitations/accept', [CompanyMemberController::class, 'accept']);

    Route::middleware(['role:nti_admin,superadmin', 'admin.audit'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        Route::get('/audit-events', [AdminAuditEventController::class, 'index']);
        Route::get('/audit-events/filters', [AdminAuditEventController::class, 'filters']);

        Route::get('/reports', [AdminReportController::class, 'index']);
        Route::get('/reports/summary', [AdminReportController::class, 'summary']);
        Route::get('/reports/{type}/csv', [AdminReportController::class, 'download']);

        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{user}', [AdminController::class, 'showUser']);
        Route::patch('/users/{user}', [AdminController::class, 'updateUser']);
        Route::patch('/users/{user}/approve', [AdminController::class, 'approveUser']);
        Route::patch('/users/{user}/reject', [AdminController::class, 'rejectUser']);
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);

        Route::get('/calls', [AdminCallController::class, 'index']);
        Route::get('/calls/{call}', [AdminCallController::class, 'show']);
        Route::post('/calls', [AdminCallController::class, 'store']);
        Route::patch('/calls/{call}', [AdminCallController::class, 'update']);
        Route::patch('/calls/{call}/open', [AdminCallController::class, 'open']);
        Route::patch('/calls/{call}/close', [AdminCallController::class, 'close']);

        Route::get('/programs', [AdminProgramController::class, 'index']);
        Route::get('/programs/{program}', [AdminProgramController::class, 'show']);
        Route::post('/programs', [AdminProgramController::class, 'store']);
        Route::patch('/programs/{program}', [AdminProgramController::class, 'update']);

        Route::get('/applications', [AdminApplicationController::class, 'index']);
        Route::get('/applications/{application}', [AdminApplicationController::class, 'show']);
        Route::patch('/applications/{application}/assign-mentor', [AdminApplicationController::class, 'assignMentor']);

        Route::get('/teams', [AdminController::class, 'teams']);
    });
});

Route::middleware(['auth:api', 'account.active'])->group(function () {
    Route::post('/program-a/teams/{team}/invitations', [TeamInvitationController::class, 'send']);
    Route::get('/program-a/teams/{team}/invitations', [TeamInvitationController::class, 'index']);
});

if (file_exists(__DIR__ . '/api-program-a.php')) {
    require __DIR__ . '/api-program-a.php';
}

if (file_exists(__DIR__ . '/api-program-b.php')) {
    require __DIR__ . '/api-program-b.php';
}