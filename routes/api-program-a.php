<?php

use App\Http\Controllers\Api\ProgramA\TeamController;
use App\Http\Controllers\Api\ProgramA\ApplicationController;
use App\Http\Controllers\Api\ProgramA\DocumentController;
use App\Http\Controllers\Api\ProgramA\EvaluationController;
use App\Http\Controllers\Api\ProgramA\MentorshipController;
use App\Http\Controllers\Api\ProgramA\ConsultationController;
use App\Http\Controllers\Api\ProgramA\MilestoneController;
use App\Http\Controllers\Api\ProgramA\CallController;
use App\Http\Controllers\Api\ProgramA\StudentProfileController;
use App\Http\Controllers\Api\ProgramA\TeamInvitationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('program-a')->name('program-a.')->group(function () {

    // Calls
    Route::get('calls', [CallController::class, 'index'])->name('calls.index');
    Route::get('calls/{call}', [CallController::class, 'show'])->name('calls.show');
    Route::post('calls', [CallController::class, 'store'])->name('calls.store');
    Route::patch('calls/{call}', [CallController::class, 'update'])->name('calls.update');

    // Teams
    Route::apiResource('teams', TeamController::class)->except(['destroy']);
    Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('teams.members.remove');

    // Team Invitations
    Route::post('teams/{team}/invitations', [TeamInvitationController::class, 'send'])->name('teams.invitations.send');
    Route::get('teams/{team}/invitations', [TeamInvitationController::class, 'index'])->name('teams.invitations.index');
    Route::post('invitations/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');

    // Applications
    Route::apiResource('applications', ApplicationController::class)->except(['destroy']);
    Route::patch('applications/{application}/transition', [ApplicationController::class, 'transition'])->name('applications.transition');

    // Documents
    Route::prefix('applications/{application}/documents')->name('applications.documents.')->group(function () {
        Route::get('/', [DocumentController::class, 'index'])->name('index');
        Route::post('/', [DocumentController::class, 'store'])->name('store');
        Route::get('/checklist', [DocumentController::class, 'checklist'])->name('checklist');
        Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download');
        Route::delete('/{document}', [DocumentController::class, 'destroy'])->name('destroy');
    });

    // Evaluations
    Route::prefix('applications/{application}/evaluations')->name('applications.evaluations.')->group(function () {
        Route::get('/', [EvaluationController::class, 'index'])->name('index');
        Route::post('/', [EvaluationController::class, 'store'])->name('store');
        Route::delete('/{evaluation}', [EvaluationController::class, 'destroy'])->name('destroy');
    });

    // Mentorships
    Route::prefix('applications/{application}/mentorships')->name('applications.mentorships.')->group(function () {
        Route::get('/', [MentorshipController::class, 'index'])->name('index');
        Route::post('/', [MentorshipController::class, 'store'])->name('store');
        Route::patch('/{mentorship}/end', [MentorshipController::class, 'end'])->name('end');
        Route::prefix('{mentorship}/consultations')->name('consultations.')->group(function () {
            Route::get('/', [ConsultationController::class, 'index'])->name('index');
            Route::post('/', [ConsultationController::class, 'store'])->name('store');
        });
    });

    // Milestones
    Route::prefix('applications/{application}/milestones')->name('applications.milestones.')->group(function () {
        Route::get('/', [MilestoneController::class, 'index'])->name('index');
        Route::post('/', [MilestoneController::class, 'store'])->name('store');
        Route::patch('/{milestone}', [MilestoneController::class, 'update'])->name('update');
        Route::delete('/{milestone}', [MilestoneController::class, 'destroy'])->name('destroy');
    });

    // Student Profile
    Route::get('profile', [StudentProfileController::class, 'show'])->name('profile.show');
    Route::patch('profile', [StudentProfileController::class, 'update'])->name('profile.update');
});

// Public invitation preview
Route::get('program-a/invitations/{token}', [TeamInvitationController::class, 'preview'])->name('program-a.invitations.preview');