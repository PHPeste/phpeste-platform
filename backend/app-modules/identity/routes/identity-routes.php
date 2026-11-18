<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPeste\Identity\Http\Controllers\AuthController;
use PHPeste\Identity\Http\Controllers\GithubController;
use PHPeste\Identity\Http\Controllers\MeController;
use PHPeste\Identity\Http\Controllers\PasswordController;

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

    Route::post('/password/forgot', [PasswordController::class, 'forgot'])
        ->middleware('throttle:password-reset')
        ->name('auth.password.forgot');

    Route::post('/password/reset', [PasswordController::class, 'reset'])
        ->middleware('throttle:password-reset')
        ->name('auth.password.reset');

    Route::put('/password', [PasswordController::class, 'update'])
        ->middleware(['auth:sanctum', 'throttle:login'])
        ->name('auth.password.update');

    Route::get('/github/redirect', [GithubController::class, 'redirect'])
        ->middleware('throttle:login')
        ->name('auth.github.redirect');

    Route::get('/github/link', [GithubController::class, 'linkRedirect'])
        ->middleware('auth:sanctum')
        ->name('auth.github.link');

    Route::post('/github/callback', [GithubController::class, 'callback'])
        ->middleware('throttle:login')
        ->name('auth.github.callback');

    Route::delete('/github', [GithubController::class, 'unlink'])
        ->middleware('auth:sanctum')
        ->name('auth.github.unlink');
});

Route::apiV1(middleware: ['auth:sanctum'])->get('/me', MeController::class)->name('me');
