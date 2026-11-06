<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPeste\Identity\Http\Controllers\AuthController;
use PHPeste\Identity\Http\Controllers\MeController;

Route::apiV1('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:login')
        ->name('auth.register');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('auth.logout');

    Route::post('/logout-all', [AuthController::class, 'logoutAll'])
        ->middleware('auth:sanctum')
        ->name('auth.logout-all');

    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
        ->middleware(['auth:sanctum', 'throttle:password-reset'])
        ->name('verification.send');
});

Route::apiV1()->middleware('auth:sanctum')->get('/me', MeController::class)->name('me');
