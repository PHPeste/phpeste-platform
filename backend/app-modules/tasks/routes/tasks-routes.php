<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPeste\Tasks\Http\Controllers\TaskBoardController;

Route::apiV1Edition('tasks')->group(function (): void {
    Route::get('/board', [TaskBoardController::class, 'board'])->name('tasks.board');
    Route::get('/link-options', [TaskBoardController::class, 'linkOptions'])->name('tasks.link-options');
    Route::post('/', [TaskBoardController::class, 'store'])->name('tasks.store');
    Route::patch('/{task}', [TaskBoardController::class, 'update'])->whereUuid('task')->name('tasks.update');
    Route::post('/{task}/move', [TaskBoardController::class, 'move'])->whereUuid('task')->name('tasks.move');
    Route::delete('/{task}', [TaskBoardController::class, 'destroy'])->whereUuid('task')->name('tasks.destroy');
    Route::post('/columns', [TaskBoardController::class, 'storeColumn'])->name('tasks.columns.store');
    Route::post('/columns/reorder', [TaskBoardController::class, 'reorderColumns'])->name('tasks.columns.reorder');
    Route::patch('/columns/{column}', [TaskBoardController::class, 'updateColumn'])->whereUuid('column')->name('tasks.columns.update');
    Route::delete('/columns/{column}', [TaskBoardController::class, 'destroyColumn'])->whereUuid('column')->name('tasks.columns.destroy');
});
