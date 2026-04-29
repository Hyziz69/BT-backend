<?php

/**
 * routes/api-program-a.php
 *
 * Program A API routes.
 * Register in RouteServiceProvider (or bootstrap/app.php in Laravel 11):
 *
 *   Route::middleware('api')
 *        ->prefix('api')
 *        ->group(base_path('routes/api-program-a.php'));
 */

use App\Http\Controllers\Api\ProgramA\TeamController;
use App\Http\Controllers\Api\ProgramA\ApplicationController;
use App\Http\Controllers\Api\ProgramA\DocumentController;
use App\Http\Controllers\Api\ProgramA\EvaluationController;
use App\Http\Controllers\Api\ProgramA\MentorshipController;
use App\Http\Controllers\Api\ProgramA\ConsultationController;
use App\Http\Controllers\Api\ProgramA\MilestoneController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('program-a')->name('program-a.')->group(function () {

    Route::apiResource('teams', TeamController::class)->except(['destroy']);

    Route::post(
        'teams/{team}/members',
        [TeamController::class, 'addMember']
    )->name('teams.members.add');

    Route::delete(
        'teams/{team}/members/{user}',
        [TeamController::class, 'removeMember']
    )->name('teams.members.remove');

    Route::apiResource('applications', ApplicationController::class)->except(['destroy']);

    Route::patch(
        'applications/{application}/transition',
        [ApplicationController::class, 'transition']
    )->name('applications.transition');

    Route::prefix('applications/{application}/documents')->name('applications.documents.')->group(function () {
        Route::get('/',             [DocumentController::class, 'index'])->name('index');
        Route::post('/',            [DocumentController::class, 'store'])->name('store');
        Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download');
        Route::delete('/{document}', [DocumentController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('applications/{application}/evaluations')->name('applications.evaluations.')->group(function () {
        Route::get('/',             [EvaluationController::class, 'index'])->name('index');
        Route::post('/',            [EvaluationController::class, 'store'])->name('store');
        Route::delete('/{evaluation}', [EvaluationController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('applications/{application}/mentorships')->name('applications.mentorships.')->group(function () {
        Route::get('/',                 [MentorshipController::class, 'index'])->name('index');
        Route::post('/',                [MentorshipController::class, 'store'])->name('store');
        Route::patch('/{mentorship}/end', [MentorshipController::class, 'end'])->name('end');

        Route::prefix('{mentorship}/consultations')->name('consultations.')->group(function () {
            Route::get('/',  [ConsultationController::class, 'index'])->name('index');
            Route::post('/', [ConsultationController::class, 'store'])->name('store');
        });
    });

    Route::prefix('applications/{application}/milestones')->name('applications.milestones.')->group(function () {
        Route::get('/',               [MilestoneController::class, 'index'])->name('index');
        Route::post('/',              [MilestoneController::class, 'store'])->name('store');
        Route::patch('/{milestone}',  [MilestoneController::class, 'update'])->name('update');
        Route::delete('/{milestone}', [MilestoneController::class, 'destroy'])->name('destroy');
    });
});