<?php

use App\Http\Controllers\Api\ProgramB\ApplicationController;
use App\Http\Controllers\Api\ProgramB\CallController;
use App\Http\Controllers\Api\ProgramB\CompanyChallengeController;
use App\Http\Controllers\Api\ProgramB\CompanyController;
use App\Http\Controllers\Api\ProgramB\ProgramController;
use App\Http\Controllers\Api\ProgramB\TeamController;
use App\Http\Controllers\StudentProfileController;
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

Route::post('applications', [ApplicationController::class, 'store']);
Route::post('applications/{application}/select', [ApplicationController::class, 'select']);
Route::post('applications/{application}/assign-mentor', [ApplicationController::class, 'assignMentor']);
Route::post('applications/{application}/assign-po', [ApplicationController::class, 'assignPo']);
Route::post('applications/{application}/approve-delivery', [ApplicationController::class, 'approveDelivery']);

Route::post('/companies/register', [CompanyController::class, 'register']);
Route::get('/companies/{company}', [CompanyController::class, 'show']);
Route::put('/companies/{company}', [CompanyController::class, 'update']);

// 2. CompanyChallenge management (company side)
Route::get('/program-b/challenges', [CompanyChallengeController::class, 'index']);
Route::get('/program-b/challenges/{challenge}', [CompanyChallengeController::class, 'show']);
Route::post('/program-b/challenges', [CompanyChallengeController::class, 'store']);
Route::put('/program-b/challenges/{challenge}', [CompanyChallengeController::class, 'update']);
Route::patch('/program-b/challenges/{challenge}/status', [CompanyChallengeController::class, 'updateStatus']);

Route::get('/profile', [StudentProfileController::class, 'show']);
Route::post('/profile', [StudentProfileController::class, 'update']);

