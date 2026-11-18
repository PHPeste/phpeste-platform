<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPeste\Ticketing\Http\Controllers\CheckoutOrderController;
use PHPeste\Ticketing\Http\Controllers\CouponController;
use PHPeste\Ticketing\Http\Controllers\OrderController;
use PHPeste\Ticketing\Http\Controllers\PublicCouponController;
use PHPeste\Ticketing\Http\Controllers\PublicTicketController;
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
    Route::get('/orders/{order}', [OrderController::class, 'show'])->whereUuid('order')->name('ticketing.orders.show');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->whereUuid('order')->name('ticketing.orders.cancel');
});

Route::apiV1PublishedEdition('checkout', 'orders')->group(function (): void {
    Route::post('/', [CheckoutOrderController::class, 'store'])->middleware('throttle:checkout')->name('ticketing.checkout.orders.store');
    Route::get('/{order}', [CheckoutOrderController::class, 'show'])->whereUuid('order')->name('ticketing.checkout.orders.show');
    Route::post('/{order}/cancel', [CheckoutOrderController::class, 'cancel'])->whereUuid('order')->middleware('throttle:checkout')->name('ticketing.checkout.orders.cancel');
});

Route::apiV1('public')->post('/editions/{edition}/coupons/check', PublicCouponController::class)
    ->whereUuid('edition')
    ->middleware('throttle:checkout')
    ->name('ticketing.public.coupons.check');

Route::apiV1('public')->get('/editions/{edition}/tickets', PublicTicketController::class)
    ->whereUuid('edition')
    ->name('ticketing.public.tickets');
