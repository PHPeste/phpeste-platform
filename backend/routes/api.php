<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PingController;
use Illuminate\Support\Facades\Route;

Route::apiV1()->group(function (): void {
    Route::get('/ping', PingController::class)->name('api.ping');
});
