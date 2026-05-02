<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\CompanyChallengeController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::post('/teams', [TeamController::class, 'store']);
Route::delete('/teams/{team}', [TeamController::class, 'destroy']);
Route::post('/teams/join', [TeamController::class, 'join']);
Route::post('/teams/{team}/leave', [TeamController::class, 'leave']);
Route::delete('/teams/{team}/members/{user}', [TeamController::class, 'kickMember']);
Route::put('/teams/{team}', [TeamController::class, 'update']);
Route::get('/teams/{team}', [TeamController::class, 'show']);
Route::get('/teams', [TeamController::class, 'index']);
Route::get('programs', [ProgramController::class, 'index']);
Route::get('calls', [CallController::class, 'index']);
Route::get('challenges', [CompanyChallengeController::class, 'index']);
Route::get('challenges/{challenge}', [CompanyChallengeController::class, 'show']);
Route::post('applications', [ApplicationController::class, 'store']);
