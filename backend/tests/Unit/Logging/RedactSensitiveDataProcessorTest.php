<?php

declare(strict_types=1);

use App\Logging\RedactSensitiveDataProcessor;
use Carbon\CarbonImmutable;
use Monolog\Level;
use Monolog\LogRecord;

function makeTestLogRecord(array $context): LogRecord
{
    return new LogRecord(
        datetime: CarbonImmutable::now(),
        channel: 'test',
        level: Level::Info,
        message: 'test',
        context: $context,
    );
}

it('redige chaves sensíveis em qualquer nível do contexto', function (): void {
    $processor = new RedactSensitiveDataProcessor;

    $result = $processor(makeTestLogRecord([
        'email' => 'ada@example.com',
        'password' => 'supersecreto123',
        'gateway' => ['api_key' => 'sk_live_abc', 'amount' => 1_990],
    ]));

    expect($result->context)->toBe([
        'email' => 'ada@example.com',
        'password' => '[REDACTED]',
        'gateway' => ['api_key' => '[REDACTED]', 'amount' => 1_990],
    ]);
});

it('não mexe em contexto sem dado sensível', function (): void {
    $processor = new RedactSensitiveDataProcessor;

    $result = $processor(makeTestLogRecord(['request_id' => 'abc-123', 'status' => 'ok']));

    expect($result->context)->toBe(['request_id' => 'abc-123', 'status' => 'ok']);
});
