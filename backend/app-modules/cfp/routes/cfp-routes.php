<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPeste\Cfp\Http\Controllers\AdminProposalController;
use PHPeste\Cfp\Http\Controllers\CfpCriterionController;
use PHPeste\Cfp\Http\Controllers\CfpSettingsController;
use PHPeste\Cfp\Http\Controllers\CfpTrackController;
use PHPeste\Cfp\Http\Controllers\EvaluationController;
use PHPeste\Cfp\Http\Controllers\PublicCfpController;
use PHPeste\Cfp\Http\Controllers\ReviewAssignmentController;
use PHPeste\Cfp\Http\Controllers\ReviewController;
use PHPeste\Cfp\Http\Controllers\SpeakerProposalController;

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

    Route::get('/proposals', [AdminProposalController::class, 'index'])->name('cfp.proposals.index');
    Route::get('/proposals/{proposal}', [AdminProposalController::class, 'show'])->whereUuid('proposal')->name('cfp.proposals.show');
    Route::patch('/proposals/{proposal}', [AdminProposalController::class, 'update'])->whereUuid('proposal')->name('cfp.proposals.update');

    Route::get('/evaluation', [EvaluationController::class, 'index'])->name('cfp.evaluation.index');
    Route::get('/evaluation/export', [EvaluationController::class, 'export'])->name('cfp.evaluation.export');

    Route::post('/assignments/auto', [ReviewAssignmentController::class, 'auto'])->name('cfp.assignments.auto');
    Route::get('/proposals/{proposal}/assignments', [ReviewAssignmentController::class, 'index'])->whereUuid('proposal')->name('cfp.assignments.index');
    Route::post('/proposals/{proposal}/assignments', [ReviewAssignmentController::class, 'store'])->whereUuid('proposal')->name('cfp.assignments.store');
    Route::delete('/proposals/{proposal}/assignments/{reviewer}', [ReviewAssignmentController::class, 'destroy'])
        ->whereUuid('proposal')
        ->whereUuid('reviewer')
        ->name('cfp.assignments.destroy');

    Route::get('/reviews/queue', [ReviewController::class, 'queue'])->name('cfp.reviews.queue');
    Route::get('/proposals/{proposal}/review', [ReviewController::class, 'show'])->whereUuid('proposal')->name('cfp.reviews.show');
    Route::put('/proposals/{proposal}/review', [ReviewController::class, 'save'])->whereUuid('proposal')->name('cfp.reviews.save');
    Route::get('/proposals/{proposal}/reviews', [ReviewController::class, 'others'])->whereUuid('proposal')->name('cfp.reviews.index');
});

Route::apiV1PublishedEdition('speaker', 'proposals', ['auth:sanctum'])->group(function (): void {
    Route::get('/', [SpeakerProposalController::class, 'index'])->name('cfp.speaker.proposals.index');
    Route::post('/', [SpeakerProposalController::class, 'store'])->name('cfp.speaker.proposals.store');
    Route::get('/{proposal}', [SpeakerProposalController::class, 'show'])->whereUuid('proposal')->name('cfp.speaker.proposals.show');
    Route::patch('/{proposal}', [SpeakerProposalController::class, 'update'])->whereUuid('proposal')->name('cfp.speaker.proposals.update');
    Route::post('/{proposal}/submit', [SpeakerProposalController::class, 'submit'])->whereUuid('proposal')->name('cfp.speaker.proposals.submit');
    Route::post('/{proposal}/withdraw', [SpeakerProposalController::class, 'withdraw'])->whereUuid('proposal')->name('cfp.speaker.proposals.withdraw');
    Route::post('/{proposal}/coauthors', [SpeakerProposalController::class, 'addCoauthor'])->whereUuid('proposal')->name('cfp.speaker.proposals.coauthors.store');
    Route::delete('/{proposal}/coauthors/{user}', [SpeakerProposalController::class, 'removeCoauthor'])
        ->whereUuid('proposal')
        ->whereUuid('user')
        ->name('cfp.speaker.proposals.coauthors.destroy');
});

Route::apiV1('public')->get('/editions/{edition}/cfp', PublicCfpController::class)
    ->whereUuid('edition')
    ->name('cfp.public.show');
