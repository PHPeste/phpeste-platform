<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPeste\Notifications\Enums\EmailTemplate;
use PHPeste\Notifications\Http\Controllers\EmailDeliveryController;
use PHPeste\Notifications\Http\Controllers\EmailSuppressionController;
use PHPeste\Notifications\Http\Controllers\EmailTemplateController;
use PHPeste\Notifications\Http\Controllers\ResendWebhookController;
use PHPeste\Notifications\Http\Controllers\UnsubscribeController;

Route::apiV1Edition('emails')->group(function (): void {
    $templates = array_column(EmailTemplate::cases(), 'value');

    Route::get('/templates', [EmailTemplateController::class, 'index'])->name('notifications.templates.index');
    Route::get('/templates/{template}', [EmailTemplateController::class, 'show'])
        ->whereIn('template', $templates)
        ->name('notifications.templates.show');
    Route::put('/templates/{template}', [EmailTemplateController::class, 'update'])
        ->whereIn('template', $templates)
        ->name('notifications.templates.update');
    Route::delete('/templates/{template}', [EmailTemplateController::class, 'destroy'])
        ->whereIn('template', $templates)
        ->name('notifications.templates.destroy');
    Route::post('/templates/{template}/preview', [EmailTemplateController::class, 'preview'])
        ->whereIn('template', $templates)
        ->name('notifications.templates.preview');
    Route::post('/templates/{template}/test', [EmailTemplateController::class, 'sendTest'])
        ->whereIn('template', $templates)
        ->middleware('throttle:password-reset')
        ->name('notifications.templates.test');

    Route::get('/deliveries', [EmailDeliveryController::class, 'index'])->name('notifications.deliveries.index');
    Route::post('/deliveries/{delivery}/resend', [EmailDeliveryController::class, 'resend'])
        ->whereUuid('delivery')
        ->name('notifications.deliveries.resend');

    Route::get('/suppressions', [EmailSuppressionController::class, 'index'])->name('notifications.suppressions.index');
    Route::delete('/suppressions/{suppression}', [EmailSuppressionController::class, 'destroy'])
        ->whereUuid('suppression')
        ->name('notifications.suppressions.destroy');
});

Route::apiV1('webhooks')->post('/resend', ResendWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('notifications.webhooks.resend');

Route::apiV1('public')->match(['get', 'post'], '/editions/{edition}/unsubscribe', UnsubscribeController::class)
    ->whereUuid('edition')
    ->middleware('signed')
    ->name('notifications.unsubscribe');
