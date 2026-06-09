<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::any('/login', function () {
    return response()->json([
        'message' => 'Unauthenticated.'
    ], 401);
})->name('login');

Route::get('/reset-password/{token}', function () {
    return response()->json(['message' => 'Use the frontend app to reset your password.']);
})->name('password.reset');