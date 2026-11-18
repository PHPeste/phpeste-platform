<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
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
});

Route::apiV1('public')->get('/editions/{edition}/tickets', PublicTicketController::class)
    ->whereUuid('edition')
    ->name('ticketing.public.tickets');
