<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPeste\Suppliers\Http\Controllers\SupplierAttachmentController;
use PHPeste\Suppliers\Http\Controllers\SupplierContactController;
use PHPeste\Suppliers\Http\Controllers\SupplierController;
use PHPeste\Suppliers\Http\Controllers\SupplierDirectoryController;
use PHPeste\Suppliers\Http\Controllers\SupplierQuoteController;

Route::apiV1Edition('suppliers')->group(function (): void {
    Route::get('/', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('/', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::get('/directory', [SupplierDirectoryController::class, 'index'])->name('suppliers.directory');
    Route::post('/directory/{supplier}', [SupplierDirectoryController::class, 'link'])->whereUuid('supplier')->name('suppliers.directory.link');
    Route::get('/attachments/{attachment}/url', [SupplierAttachmentController::class, 'url'])->whereUuid('attachment')->name('suppliers.attachments.url');
    Route::delete('/attachments/{attachment}', [SupplierAttachmentController::class, 'destroy'])->whereUuid('attachment')->name('suppliers.attachments.destroy');

    Route::get('/{link}', [SupplierController::class, 'show'])->whereUuid('link')->name('suppliers.show');
    Route::patch('/{link}', [SupplierController::class, 'update'])->whereUuid('link')->name('suppliers.update');
    Route::delete('/{link}', [SupplierController::class, 'destroy'])->whereUuid('link')->name('suppliers.destroy');
    Route::post('/{link}/status', [SupplierController::class, 'status'])->whereUuid('link')->name('suppliers.status');
    Route::post('/{link}/activities', [SupplierController::class, 'logActivity'])->whereUuid('link')->name('suppliers.activities.store');
    Route::post('/{link}/contacts', [SupplierContactController::class, 'store'])->whereUuid('link')->name('suppliers.contacts.store');
    Route::patch('/{link}/contacts/{contact}', [SupplierContactController::class, 'update'])->whereUuid(['link', 'contact'])->name('suppliers.contacts.update');
    Route::delete('/{link}/contacts/{contact}', [SupplierContactController::class, 'destroy'])->whereUuid(['link', 'contact'])->name('suppliers.contacts.destroy');
    Route::post('/{link}/quotes', [SupplierQuoteController::class, 'store'])->whereUuid('link')->name('suppliers.quotes.store');
    Route::post('/{link}/quotes/{quote}/reject', [SupplierQuoteController::class, 'reject'])->whereUuid(['link', 'quote'])->name('suppliers.quotes.reject');
    Route::delete('/{link}/quotes/{quote}', [SupplierQuoteController::class, 'destroy'])->whereUuid(['link', 'quote'])->name('suppliers.quotes.destroy');
    Route::post('/{link}/attachments', [SupplierAttachmentController::class, 'store'])->whereUuid('link')->name('suppliers.attachments.store');
});

Route::apiV1('suppliers')->get('/attachments/{attachment}/download', [SupplierAttachmentController::class, 'download'])
    ->whereUuid('attachment')
    ->middleware(['signed', 'throttle:60,1'])
    ->name('suppliers.attachments.download');
