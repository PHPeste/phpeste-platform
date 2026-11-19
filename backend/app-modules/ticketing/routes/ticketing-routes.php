<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPeste\Ticketing\Http\Controllers\AttendeeTicketController;
use PHPeste\Ticketing\Http\Controllers\CheckoutOrderController;
use PHPeste\Ticketing\Http\Controllers\CouponController;
use PHPeste\Ticketing\Http\Controllers\OrderController;
use PHPeste\Ticketing\Http\Controllers\OrderRefundController;
use PHPeste\Ticketing\Http\Controllers\PublicCouponController;
use PHPeste\Ticketing\Http\Controllers\PublicTicketController;
use PHPeste\Ticketing\Http\Controllers\ReconciliationController;
use PHPeste\Ticketing\Http\Controllers\TicketController;
use PHPeste\Ticketing\Http\Controllers\TicketImportController;
use PHPeste\Ticketing\Http\Controllers\TicketTypeController;

Route::apiV1Edition('ticketing')->group(function (): void {
    Route::get('/ticket-types', [TicketTypeController::class, 'index'])->name('ticketing.types.index');
    Route::patch('/settings', [TicketTypeController::class, 'updateSettings'])->name('ticketing.settings.update');
    Route::post('/ticket-types', [TicketTypeController::class, 'store'])->name('ticketing.types.store');
    Route::patch('/ticket-types/{ticketType}', [TicketTypeController::class, 'update'])->whereUuid('ticketType')->name('ticketing.types.update');
    Route::delete('/ticket-types/{ticketType}', [TicketTypeController::class, 'destroy'])->whereUuid('ticketType')->name('ticketing.types.destroy');
    Route::post('/ticket-types/{ticketType}/batches', [TicketTypeController::class, 'storeBatch'])->whereUuid('ticketType')->name('ticketing.batches.store');
    Route::patch('/ticket-types/{ticketType}/batches/{batch}', [TicketTypeController::class, 'updateBatch'])->whereUuid(['ticketType', 'batch'])->name('ticketing.batches.update');
    Route::post('/ticket-types/{ticketType}/batches/{batch}/close', [TicketTypeController::class, 'closeBatch'])->whereUuid(['ticketType', 'batch'])->name('ticketing.batches.close');
    Route::delete('/ticket-types/{ticketType}/batches/{batch}', [TicketTypeController::class, 'destroyBatch'])->whereUuid(['ticketType', 'batch'])->name('ticketing.batches.destroy');
    Route::get('/coupons', [CouponController::class, 'index'])->name('ticketing.coupons.index');
    Route::post('/coupons', [CouponController::class, 'store'])->name('ticketing.coupons.store');
    Route::patch('/coupons/{coupon}', [CouponController::class, 'update'])->whereUuid('coupon')->name('ticketing.coupons.update');
    Route::delete('/coupons/{coupon}', [CouponController::class, 'destroy'])->whereUuid('coupon')->name('ticketing.coupons.destroy');
    Route::get('/coupons/{coupon}/redemptions', [CouponController::class, 'redemptions'])->whereUuid('coupon')->name('ticketing.coupons.redemptions');
    Route::get('/orders', [OrderController::class, 'index'])->name('ticketing.orders.index');
    Route::get('/orders/export', [OrderController::class, 'export'])->name('ticketing.orders.export');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->whereUuid('order')->name('ticketing.orders.show');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->whereUuid('order')->name('ticketing.orders.cancel');
    Route::post('/orders/{order}/mark-paid', [OrderController::class, 'markPaid'])->whereUuid('order')->name('ticketing.orders.mark-paid');
    Route::post('/orders/{order}/payment-reminder', [OrderController::class, 'remind'])->whereUuid('order')->middleware('throttle:6,1')->name('ticketing.orders.payment-reminder');
    Route::get('/orders/{order}/history', [OrderController::class, 'history'])->whereUuid('order')->name('ticketing.orders.history');
    Route::get('/orders/{order}/refunds', [OrderRefundController::class, 'index'])->whereUuid('order')->name('ticketing.orders.refunds.index');
    Route::post('/orders/{order}/refunds', [OrderRefundController::class, 'store'])->whereUuid('order')->name('ticketing.orders.refunds.store');
    Route::post('/orders/{order}/refunds/{refund}/settle', [OrderRefundController::class, 'settle'])->whereUuid(['order', 'refund'])->name('ticketing.orders.refunds.settle');
    Route::get('/tickets', [TicketController::class, 'index'])->name('ticketing.tickets.index');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->whereUuid('ticket')->name('ticketing.tickets.show');
    Route::patch('/tickets/{ticket}', [TicketController::class, 'update'])->whereUuid('ticket')->name('ticketing.tickets.update');
    Route::post('/tickets/{ticket}/transfer', [TicketController::class, 'transfer'])->whereUuid('ticket')->name('ticketing.tickets.transfer');
    Route::post('/tickets/{ticket}/resend', [TicketController::class, 'resend'])->whereUuid('ticket')->middleware('throttle:6,1')->name('ticketing.tickets.resend');
    Route::get('/reconciliation', [ReconciliationController::class, 'index'])->name('ticketing.reconciliation.index');
    Route::post('/reconciliation/run', [ReconciliationController::class, 'run'])->name('ticketing.reconciliation.run');
    Route::get('/imports', [TicketImportController::class, 'index'])->name('ticketing.imports.index');
    Route::post('/imports', [TicketImportController::class, 'store'])->name('ticketing.imports.store');
    Route::get('/imports/{import}', [TicketImportController::class, 'show'])->whereUuid('import')->name('ticketing.imports.show');
    Route::put('/imports/{import}/mapping', [TicketImportController::class, 'map'])->whereUuid('import')->name('ticketing.imports.map');
    Route::post('/imports/{import}/start', [TicketImportController::class, 'start'])->whereUuid('import')->name('ticketing.imports.start');
    Route::post('/imports/{import}/resume', [TicketImportController::class, 'resume'])->whereUuid('import')->name('ticketing.imports.resume');
    Route::delete('/imports/{import}', [TicketImportController::class, 'destroy'])->whereUuid('import')->name('ticketing.imports.destroy');
    Route::get('/imports/{import}/rejections', [TicketImportController::class, 'rejections'])->whereUuid('import')->name('ticketing.imports.rejections');
    Route::get('/imports/{import}/rejections.csv', [TicketImportController::class, 'rejectionsCsv'])->whereUuid('import')->name('ticketing.imports.rejections.csv');
    Route::post('/imports/{import}/email', [TicketImportController::class, 'email'])->whereUuid('import')->middleware('throttle:6,1')->name('ticketing.imports.email');
    Route::post('/reconciliation/{issue}/resolve', [ReconciliationController::class, 'resolve'])->whereUuid('issue')->name('ticketing.reconciliation.resolve');
});

Route::apiV1PublishedEdition('checkout', 'orders')->group(function (): void {
    Route::post('/', [CheckoutOrderController::class, 'store'])->middleware('throttle:checkout')->name('ticketing.checkout.orders.store');
    Route::get('/{order}', [CheckoutOrderController::class, 'show'])->whereUuid('order')->name('ticketing.checkout.orders.show');
    Route::post('/{order}/payment', [CheckoutOrderController::class, 'startPayment'])->whereUuid('order')->middleware('throttle:checkout')->name('ticketing.checkout.orders.payment.start');
    Route::get('/{order}/payment', [CheckoutOrderController::class, 'showPayment'])->whereUuid('order')->name('ticketing.checkout.orders.payment.show');
    Route::post('/{order}/cancel', [CheckoutOrderController::class, 'cancel'])->whereUuid('order')->middleware('throttle:checkout')->name('ticketing.checkout.orders.cancel');
});

Route::apiV1('public')->post('/editions/{edition}/coupons/check', PublicCouponController::class)
    ->whereUuid('edition')
    ->middleware('throttle:checkout')
    ->name('ticketing.public.coupons.check');

Route::apiV1('public')->get('/editions/{edition}/tickets', PublicTicketController::class)
    ->whereUuid('edition')
    ->name('ticketing.public.tickets');

Route::apiV1PublishedEdition('attendee', 'tickets', ['auth:sanctum'])->group(function (): void {
    Route::get('/', [AttendeeTicketController::class, 'index'])->name('ticketing.attendee.tickets.index');
    Route::get('/{ticket}', [AttendeeTicketController::class, 'show'])->whereUuid('ticket')->name('ticketing.attendee.tickets.show');
    Route::patch('/{ticket}', [AttendeeTicketController::class, 'update'])->whereUuid('ticket')->name('ticketing.attendee.tickets.update');
    Route::post('/{ticket}/transfer', [AttendeeTicketController::class, 'transfer'])->whereUuid('ticket')->middleware('throttle:6,1')->name('ticketing.attendee.tickets.transfer');
    Route::post('/{ticket}/resend', [AttendeeTicketController::class, 'resend'])->whereUuid('ticket')->middleware('throttle:3,1')->name('ticketing.attendee.tickets.resend');
});
