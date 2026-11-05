<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

it('escreve log em JSON, com request_id e sem segredo', function (): void {
    $logPath = storage_path('logs/laravel.log');
    File::delete($logPath);

    Log::withContext(['request_id' => 'test-request-id']);
    Log::info('tentativa de login', [
        'email' => 'ada@example.com',
        'password' => 'supersecreto123',
    ]);

    $lastLine = mb_trim((string) collect(file($logPath))->last());
    $decoded = json_decode($lastLine, true);

    expect($decoded)->not->toBeNull()
        ->and($decoded['context']['request_id'])->toBe('test-request-id')
        ->and($decoded['context']['password'])->toBe('[REDACTED]')
        ->and($lastLine)->not->toContain('supersecreto123');
});
