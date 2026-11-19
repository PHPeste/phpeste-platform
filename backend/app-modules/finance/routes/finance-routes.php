<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPeste\Finance\Http\Controllers\FinanceController;

Route::apiV1Edition('finance')->group(function (): void {
    Route::get('/summary', [FinanceController::class, 'summary'])->name('finance.summary');
    Route::get('/export', [FinanceController::class, 'export'])->middleware('throttle:10,1')->name('finance.export');
    Route::get('/suppliers', [FinanceController::class, 'suppliers'])->name('finance.suppliers');
    Route::get('/categories', [FinanceController::class, 'categories'])->name('finance.categories.index');
    Route::post('/categories', [FinanceController::class, 'storeCategory'])->name('finance.categories.store');
    Route::patch('/categories/{category}', [FinanceController::class, 'updateCategory'])->whereUuid('category')->name('finance.categories.update');
    Route::delete('/categories/{category}', [FinanceController::class, 'destroyCategory'])->whereUuid('category')->name('finance.categories.destroy');
    Route::get('/expenses', [FinanceController::class, 'expenses'])->name('finance.expenses.index');
    Route::post('/expenses', [FinanceController::class, 'storeExpense'])->name('finance.expenses.store');
    Route::patch('/expenses/{expense}', [FinanceController::class, 'updateExpense'])->whereUuid('expense')->name('finance.expenses.update');
    Route::delete('/expenses/{expense}', [FinanceController::class, 'destroyExpense'])->whereUuid('expense')->name('finance.expenses.destroy');
    Route::post('/expenses/{expense}/receipt', [FinanceController::class, 'uploadReceipt'])->whereUuid('expense')->name('finance.expenses.receipt');
    Route::get('/expenses/{expense}/receipt/url', [FinanceController::class, 'receiptUrl'])->whereUuid('expense')->name('finance.expenses.receipt.url');
});

Route::apiV1('finance')->get('/receipts/{expense}/download', [FinanceController::class, 'downloadReceipt'])
    ->whereUuid('expense')
    ->middleware(['signed', 'throttle:60,1'])
    ->name('finance.receipts.download');
