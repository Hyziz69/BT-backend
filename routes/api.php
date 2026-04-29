<?php

use App\Http\Controllers\Api\AdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:nti_admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/users', [AdminController::class, 'users']);
    Route::get('/users/{user}', [AdminController::class, 'showUser']);
});