<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPeste\Payments\Http\Controllers\PaymentAccountController;
use PHPeste\Payments\Http\Controllers\PublicReceiverController;
use PHPeste\Payments\Http\Controllers\WebhookController;

Route::apiV1Edition('payments')->group(function (): void {
    Route::get('/accounts', [PaymentAccountController::class, 'index'])->name('payments.accounts.index');
    Route::post('/accounts', [PaymentAccountController::class, 'store'])->name('payments.accounts.store');
    Route::patch('/accounts/{account}', [PaymentAccountController::class, 'update'])->whereUuid('account')->name('payments.accounts.update');
    Route::post('/accounts/{account}/verify', [PaymentAccountController::class, 'verify'])->whereUuid('account')->name('payments.accounts.verify');
});

Route::apiV1('public')->get('/editions/{edition}/payments/receiver', PublicReceiverController::class)
    ->whereUuid('edition')
    ->name('payments.public.receiver');

Route::apiV1('webhooks')->post('/payments/{token}', WebhookController::class)
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:webhooks')
    ->name('payments.webhooks.receive');
