<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPeste\Sponsors\Http\Controllers\SponsorAttachmentController;
use PHPeste\Sponsors\Http\Controllers\SponsorBoardController;
use PHPeste\Sponsors\Http\Controllers\SponsorController;
use PHPeste\Sponsors\Http\Controllers\SponsorDeliverableController;
use PHPeste\Sponsors\Http\Controllers\SponsorshipController;
use PHPeste\Sponsors\Http\Controllers\SponsorTierController;

Route::apiV1Edition('sponsors')->group(function (): void {
    Route::get('/board', [SponsorBoardController::class, 'show'])->name('sponsors.board');
    Route::patch('/settings', [SponsorBoardController::class, 'settings'])->name('sponsors.settings');

    Route::get('/tiers', [SponsorTierController::class, 'index'])->name('sponsors.tiers.index');
    Route::post('/tiers', [SponsorTierController::class, 'store'])->name('sponsors.tiers.store');
    Route::patch('/tiers/{tier}', [SponsorTierController::class, 'update'])->whereUuid('tier')->name('sponsors.tiers.update');
    Route::delete('/tiers/{tier}', [SponsorTierController::class, 'destroy'])->whereUuid('tier')->name('sponsors.tiers.destroy');

    Route::get('/companies', [SponsorController::class, 'index'])->name('sponsors.companies.index');
    Route::post('/companies', [SponsorController::class, 'store'])->name('sponsors.companies.store');
    Route::get('/companies/{sponsor}', [SponsorController::class, 'show'])->whereUuid('sponsor')->name('sponsors.companies.show');
    Route::patch('/companies/{sponsor}', [SponsorController::class, 'update'])->whereUuid('sponsor')->name('sponsors.companies.update');
    Route::delete('/companies/{sponsor}', [SponsorController::class, 'destroy'])->whereUuid('sponsor')->name('sponsors.companies.destroy');
    Route::post('/companies/{sponsor}/contacts', [SponsorController::class, 'logContact'])->whereUuid('sponsor')->name('sponsors.contacts.store');
    Route::delete('/companies/{sponsor}/contacts/{contact}', [SponsorController::class, 'deleteContact'])->whereUuid(['sponsor', 'contact'])->name('sponsors.contacts.destroy');
    Route::post('/companies/{sponsor}/attachments', [SponsorAttachmentController::class, 'store'])->whereUuid('sponsor')->name('sponsors.attachments.store');
    Route::post('/companies/{sponsor}/sponsorships', [SponsorshipController::class, 'store'])->whereUuid('sponsor')->name('sponsors.sponsorships.store');

    Route::get('/attachments/{attachment}/url', [SponsorAttachmentController::class, 'url'])->whereUuid('attachment')->name('sponsors.attachments.url');
    Route::delete('/attachments/{attachment}', [SponsorAttachmentController::class, 'destroy'])->whereUuid('attachment')->name('sponsors.attachments.destroy');

    Route::get('/sponsorships/{sponsorship}', [SponsorshipController::class, 'show'])->whereUuid('sponsorship')->name('sponsors.sponsorships.show');
    Route::patch('/sponsorships/{sponsorship}', [SponsorshipController::class, 'update'])->whereUuid('sponsorship')->name('sponsors.sponsorships.update');
    Route::post('/sponsorships/{sponsorship}/move', [SponsorshipController::class, 'move'])->whereUuid('sponsorship')->name('sponsors.sponsorships.move');
    Route::delete('/sponsorships/{sponsorship}', [SponsorshipController::class, 'destroy'])->whereUuid('sponsorship')->name('sponsors.sponsorships.destroy');
    Route::post('/sponsorships/{sponsorship}/payments', [SponsorshipController::class, 'recordPayment'])->whereUuid('sponsorship')->name('sponsors.payments.store');
    Route::delete('/sponsorships/{sponsorship}/payments/{payment}', [SponsorshipController::class, 'deletePayment'])->whereUuid(['sponsorship', 'payment'])->name('sponsors.payments.destroy');
    Route::post('/sponsorships/{sponsorship}/deliverables', [SponsorDeliverableController::class, 'store'])->whereUuid('sponsorship')->name('sponsors.deliverables.store');

    Route::get('/deliverables', [SponsorDeliverableController::class, 'index'])->name('sponsors.deliverables.index');
    Route::patch('/deliverables/{deliverable}', [SponsorDeliverableController::class, 'update'])->whereUuid('deliverable')->name('sponsors.deliverables.update');
    Route::delete('/deliverables/{deliverable}', [SponsorDeliverableController::class, 'destroy'])->whereUuid('deliverable')->name('sponsors.deliverables.destroy');
});

Route::apiV1('sponsors')->get('/attachments/{attachment}/download', [SponsorAttachmentController::class, 'download'])
    ->whereUuid('attachment')
    ->middleware(['signed', 'throttle:60,1'])
    ->name('sponsors.attachments.download');
