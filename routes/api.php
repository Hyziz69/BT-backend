<?php

use App\Http\Controllers\Api\Admin\AdminApplicationController;
use App\Http\Controllers\Api\Admin\AdminAuditEventController;
use App\Http\Controllers\Api\Admin\AdminCallController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\AdminContentController;
use App\Http\Controllers\Api\Admin\AdminProgramController;
use App\Http\Controllers\Api\Admin\AdminReportController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Mentor\MentorshipController as MentorMentorshipController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PublicContentController;
use App\Http\Controllers\Api\ProgramA\TeamInvitationController;
use App\Http\Controllers\Api\ProgramB\CompanyMemberController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GdprController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/reset', [AuthController::class, 'forgotPassword']);
Route::post('/password/reset/confirm', [AuthController::class, 'resetPassword']);

Route::get('/content', [PublicContentController::class, 'index']);

Route::get('/program-a/invitations/{token}', [TeamInvitationController::class, 'preview']);
Route::get('/program-b/companies/invitations/{token}', [CompanyMemberController::class, 'preview']);

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/gdpr/export', [GdprController::class, 'export']);
    Route::post('/gdpr/request-deletion', [GdprController::class, 'requestDeletion']);
    Route::post('/gdpr/cancel-deletion', [GdprController::class, 'cancelDeletion']);

    Route::get('/users', [UserController::class, 'index']);

    Route::prefix('mentor')->group(function () {
        Route::get('/mentorships', [MentorMentorshipController::class, 'index']);
        Route::get('/mentorships/{mentorship}', [MentorMentorshipController::class, 'show']);
        Route::post('/mentorships/{mentorship}/consultations', [MentorMentorshipController::class, 'storeConsultation']);
    });

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

        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::post('/users/{id}/approve-deletion', [AdminController::class, 'approveDeletion']);
        Route::post('/users/{id}/reject-deletion', [AdminController::class, 'rejectDeletion']);

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
        Route::patch('/applications/{application}/status', [AdminApplicationController::class, 'updateStatus']);

        Route::get('/teams', [AdminController::class, 'teams']);

        Route::get('/content', [AdminContentController::class, 'index']);
        Route::patch('/content/{key}', [AdminContentController::class, 'update']);
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