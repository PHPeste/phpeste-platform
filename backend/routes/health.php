<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Fora do grupo `web` de propósito: não pode depender de sessão (Redis) pra
// responder — isso quebraria o próprio healthcheck se o Redis caísse.
Route::get('/up', HealthController::class)->name('health');
