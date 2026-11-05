<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware(['api', 'throttle:login'])
        ->post('/__test/login', fn () => response()->json(['ok' => true]));
});

it('responde 429 depois de estourar o limite do limiter de login', function (): void {
    $attempts = (int) config('security.rate_limits.login.attempts');

    for ($i = 0; $i < $attempts; $i++) {
        $this->postJson('/__test/login', ['email' => 'ada@example.com'])->assertOk();
    }

    $this->postJson('/__test/login', ['email' => 'ada@example.com'])
        ->assertStatus(429);
});

it('não compartilha o limite entre e-mails diferentes', function (): void {
    $attempts = (int) config('security.rate_limits.login.attempts');

    for ($i = 0; $i < $attempts; $i++) {
        $this->postJson('/__test/login', ['email' => 'ada@example.com'])->assertOk();
    }

    $this->postJson('/__test/login', ['email' => 'ada@example.com'])->assertStatus(429);
    $this->postJson('/__test/login', ['email' => 'outra@example.com'])->assertOk();
});
