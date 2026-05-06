<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ProgramA\TeamInvitationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/reset', [AuthController::class, 'forgotPassword']);
Route::post('/password/reset/confirm', [AuthController::class, 'resetPassword']);
Route::get('/program-a/invitations/{token}', [TeamInvitationController::class, 'preview']);

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/program-a/invitations/accept', [TeamInvitationController::class, 'accept']);
    Route::middleware('role:nti_admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{user}', [AdminController::class, 'showUser']);
        Route::patch('/users/{user}', [AdminController::class, 'updateUser']);
    });
});

Route::middleware(['auth:api', 'account.active'])->group(function () {
    Route::post('/program-a/teams/{team}/invitations', [TeamInvitationController::class, 'send']);
    Route::get('/program-a/teams/{team}/invitations', [TeamInvitationController::class, 'index']);
});

require __DIR__ . '/api-program-a.php';