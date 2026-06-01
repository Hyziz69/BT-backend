<?php

use App\Http\Controllers\Api\ProgramB\ApplicationController;
use App\Http\Controllers\Api\ProgramB\CallController;
use App\Http\Controllers\Api\ProgramB\CompanyChallengeController;
use App\Http\Controllers\Api\ProgramB\CompanyController;
use App\Http\Controllers\Api\ProgramB\MentorshipController;
use App\Http\Controllers\Api\ProgramB\ProgramController;
use App\Http\Controllers\Api\ProgramB\TeamController;
use App\Http\Controllers\StudentProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('program-b')->name('program-b.')->group(function () {

    // Programs & Calls
    Route::get('programs', [ProgramController::class, 'index'])->name('programs.index');
    Route::get('calls', [CallController::class, 'index'])->name('calls.index');

    // Teams
    Route::prefix('teams')->name('teams.')->group(function () {
        Route::get('/', [TeamController::class, 'index'])->name('index');
        Route::post('/', [TeamController::class, 'store'])->name('store');
        Route::get('/{team}', [TeamController::class, 'show'])->name('show');
        Route::put('/{team}', [TeamController::class, 'update'])->name('update');
        Route::delete('/{team}', [TeamController::class, 'destroy'])->name('destroy');
        Route::post('/join', [TeamController::class, 'join'])->name('join');
        Route::post('/{team}/leave', [TeamController::class, 'leave'])->name('leave');
        Route::delete('/{team}/members/{user}', [TeamController::class, 'kickMember'])->name('members.kick');
    });

    // Applications
    Route::prefix('applications')->name('applications.')->group(function () {
        Route::get('/', [ApplicationController::class, 'index'])->name('index');
        Route::get('/{application}', [ApplicationController::class, 'show'])->name('show');
        Route::post('/', [ApplicationController::class, 'store'])->name('store');
        Route::post('/{application}/select', [ApplicationController::class, 'select'])->name('select');
//        Route::post('/{application}/assign-mentor', [ApplicationController::class, 'assignMentor'])->name('assign-mentor');
        Route::post('/{application}/assign-po', [ApplicationController::class, 'assignPo'])->name('assign-po');
        Route::post('/{application}/approve-delivery', [ApplicationController::class, 'approveDelivery'])->name('approve-delivery');
        Route::patch('/{application}/transition', [ApplicationController::class, 'transition'])
            ->name('transition');
    });

    //Mentorship
    Route::prefix('applications/{application}/mentorships')->name('applications.mentorships.')->group(function () {
        Route::get('/', [MentorshipController::class, 'index'])->name('index');
        Route::post('/', [MentorshipController::class, 'store'])->name('store');
        Route::patch('/{mentorship}/end', [MentorshipController::class, 'end'])->name('end');
    });

    // Companies
    Route::prefix('companies')->name('companies.')->group(function () {
        Route::post('/register', [CompanyController::class, 'register'])->name('register');
        Route::get('/{company}', [CompanyController::class, 'show'])->name('show');
        Route::put('/{company}', [CompanyController::class, 'update'])->name('update');
    });

    // Company Challenges
    Route::prefix('challenges')->name('challenges.')->group(function () {
        Route::get('/', [CompanyChallengeController::class, 'index'])->name('index');
        Route::post('/', [CompanyChallengeController::class, 'store'])->name('store');
        Route::get('/{challenge}', [CompanyChallengeController::class, 'show'])->name('show');
        Route::put('/{challenge}', [CompanyChallengeController::class, 'update'])->name('update');
        Route::patch('/{challenge}/status', [CompanyChallengeController::class, 'updateStatus'])->name('status.update');
    });

    // Student Profile
    Route::get('/profile', [StudentProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [StudentProfileController::class, 'update'])->name('profile.update');
});

