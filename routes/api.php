<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AdminController;

// базовый user endpoint (друга)
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// твои admin routes
Route::middleware(['auth:sanctum', 'role:nti_admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/users', [AdminController::class, 'users']);
    Route::get('/users/{user}', [AdminController::class, 'showUser']);
});