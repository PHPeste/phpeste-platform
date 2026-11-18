<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPeste\Cfp\Http\Controllers\CfpCriterionController;
use PHPeste\Cfp\Http\Controllers\CfpSettingsController;
use PHPeste\Cfp\Http\Controllers\CfpTrackController;
use PHPeste\Cfp\Http\Controllers\PublicCfpController;

Route::apiV1Edition('cfp')->group(function (): void {
    Route::get('/settings', [CfpSettingsController::class, 'show'])->name('cfp.settings.show');
    Route::patch('/settings', [CfpSettingsController::class, 'update'])->name('cfp.settings.update');

    Route::get('/tracks', [CfpTrackController::class, 'index'])->name('cfp.tracks.index');
    Route::post('/tracks', [CfpTrackController::class, 'store'])->name('cfp.tracks.store');
    Route::patch('/tracks/{track}', [CfpTrackController::class, 'update'])->whereUuid('track')->name('cfp.tracks.update');
    Route::delete('/tracks/{track}', [CfpTrackController::class, 'destroy'])->whereUuid('track')->name('cfp.tracks.destroy');

    Route::get('/criteria', [CfpCriterionController::class, 'index'])->name('cfp.criteria.index');
    Route::post('/criteria', [CfpCriterionController::class, 'store'])->name('cfp.criteria.store');
    Route::patch('/criteria/{criterion}', [CfpCriterionController::class, 'update'])->whereUuid('criterion')->name('cfp.criteria.update');
    Route::delete('/criteria/{criterion}', [CfpCriterionController::class, 'destroy'])->whereUuid('criterion')->name('cfp.criteria.destroy');
});

Route::apiV1('public')->get('/editions/{edition}/cfp', PublicCfpController::class)
    ->whereUuid('edition')
    ->name('cfp.public.show');
