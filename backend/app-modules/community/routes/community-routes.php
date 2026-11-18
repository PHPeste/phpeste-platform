<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPeste\Community\Http\Controllers\CommunityController;
use PHPeste\Community\Http\Controllers\EditionController;
use PHPeste\Community\Http\Controllers\EditionStatusController;
use PHPeste\Community\Http\Controllers\PublicEditionController;

Route::apiV1(middleware: ['auth:sanctum'])->group(function (): void {
    Route::get('/communities', [CommunityController::class, 'index'])->name('community.communities.index');
    Route::post('/communities', [CommunityController::class, 'store'])
        ->middleware('verified')
        ->name('community.communities.store');
    Route::get('/communities/{community}', [CommunityController::class, 'show'])->name('community.communities.show');
    Route::patch('/communities/{community}', [CommunityController::class, 'update'])->name('community.communities.update');
    Route::delete('/communities/{community}', [CommunityController::class, 'destroy'])->name('community.communities.destroy');

    Route::get('/communities/{community}/editions', [EditionController::class, 'indexForCommunity'])
        ->name('community.communities.editions.index');
    Route::post('/communities/{community}/editions', [EditionController::class, 'store'])
        ->name('community.communities.editions.store');

    Route::get('/editions', [EditionController::class, 'index'])->name('community.editions.index');
});

Route::apiV1Edition()->group(function (): void {
    Route::get('/', [EditionController::class, 'show'])->name('community.editions.show');
    Route::patch('/', [EditionController::class, 'update'])->name('community.editions.update');
    Route::delete('/', [EditionController::class, 'destroy'])->name('community.editions.destroy');
    Route::post('/status', EditionStatusController::class)->name('community.editions.status');
});

Route::apiV1('public')->group(function (): void {
    Route::get('/communities/{community:slug}/editions/{edition:slug}', PublicEditionController::class)
        ->scopeBindings()
        ->name('community.public.editions.show');
});
