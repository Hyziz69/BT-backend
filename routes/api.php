<?php


use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register',               [AuthController::class, 'register']);
Route::post('/login',                  [AuthController::class, 'login']);
Route::post('/password/reset',         [AuthController::class, 'forgotPassword']);
Route::post('/password/reset/confirm', [AuthController::class, 'resetPassword']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify');

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    Route::get('/users',   [UserController::class, 'index']);
});
