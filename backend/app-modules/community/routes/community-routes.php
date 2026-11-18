<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPeste\Community\Http\Controllers\CommunityController;
use PHPeste\Community\Http\Controllers\EditionCloneController;
use PHPeste\Community\Http\Controllers\EditionController;
use PHPeste\Community\Http\Controllers\EditionStatusController;
use PHPeste\Community\Http\Controllers\InvitationController;
use PHPeste\Community\Http\Controllers\PublicEditionController;
use PHPeste\Community\Http\Controllers\RoleController;
use PHPeste\Community\Http\Controllers\TeamController;

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
    Route::get('/roles', RoleController::class)->name('community.roles.index');
    Route::get('/edition-clones/{clone}', [EditionCloneController::class, 'show'])->whereUuid('clone')->name('community.edition-clones.show');
});

Route::apiV1Edition('team')->group(function (): void {
    Route::get('/', [TeamController::class, 'index'])->name('community.team.index');
    Route::patch('/members/{member}', [TeamController::class, 'changeRole'])->whereUuid('member')->name('community.team.members.update');
    Route::delete('/members/{member}', [TeamController::class, 'removeMember'])->whereUuid('member')->name('community.team.members.destroy');
    Route::post('/invitations', [TeamController::class, 'invite'])->name('community.team.invitations.store');
    Route::post('/invitations/{invitation}/resend', [TeamController::class, 'resendInvitation'])
        ->whereUuid('invitation')
        ->name('community.team.invitations.resend');
    Route::delete('/invitations/{invitation}', [TeamController::class, 'revokeInvitation'])
        ->whereUuid('invitation')
        ->name('community.team.invitations.destroy');
});

Route::apiV1('invitations')->group(function (): void {
    Route::get('/{token}', [InvitationController::class, 'show'])
        ->middleware('throttle:password-reset')
        ->name('community.invitations.show');
    Route::post('/{token}/accept', [InvitationController::class, 'accept'])
        ->middleware('throttle:login')
        ->name('community.invitations.accept');
});

Route::apiV1Edition()->group(function (): void {
    Route::get('/', [EditionController::class, 'show'])->name('community.editions.show');
    Route::patch('/', [EditionController::class, 'update'])->name('community.editions.update');
    Route::delete('/', [EditionController::class, 'destroy'])->name('community.editions.destroy');
    Route::post('/status', EditionStatusController::class)->name('community.editions.status');
    Route::get('/clone-options', [EditionCloneController::class, 'options'])->name('community.editions.clone-options');
    Route::post('/clone', [EditionCloneController::class, 'store'])->name('community.editions.clone');
});

Route::apiV1('public')->group(function (): void {
    Route::get('/communities/{community:slug}/editions/{edition:slug}', PublicEditionController::class)
        ->scopeBindings()
        ->name('community.public.editions.show');
});
