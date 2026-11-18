<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPeste\Branding\Enums\LogoSlot;
use PHPeste\Branding\Http\Controllers\PublicThemeController;
use PHPeste\Branding\Http\Controllers\ThemeController;
use PHPeste\Branding\Http\Controllers\ThemeLogoController;
use PHPeste\Branding\Http\Controllers\ThemePresetController;

Route::apiV1Edition('theme')->group(function (): void {
    Route::get('/', [ThemeController::class, 'show'])->name('branding.theme.show');
    Route::put('/draft', [ThemeController::class, 'updateDraft'])->name('branding.theme.draft.update');
    Route::post('/draft/preset', [ThemeController::class, 'applyPreset'])->name('branding.theme.draft.preset');
    Route::delete('/draft', [ThemeController::class, 'discardDraft'])->name('branding.theme.draft.discard');
    Route::post('/publish', [ThemeController::class, 'publish'])->name('branding.theme.publish');
    Route::post('/revert', [ThemeController::class, 'revert'])->name('branding.theme.revert');

    Route::post('/logos/{slot}', [ThemeLogoController::class, 'store'])
        ->whereIn('slot', array_column(LogoSlot::cases(), 'value'))
        ->name('branding.theme.logos.store');
    Route::delete('/logos/{slot}', [ThemeLogoController::class, 'destroy'])
        ->whereIn('slot', array_column(LogoSlot::cases(), 'value'))
        ->name('branding.theme.logos.destroy');
});

Route::apiV1('branding', ['auth:sanctum'])->get('/presets', ThemePresetController::class)->name('branding.presets.index');

Route::apiV1('public')->get('/editions/{edition}/theme', PublicThemeController::class)
    ->whereUuid('edition')
    ->name('branding.public.theme');
