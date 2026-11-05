<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RedactSensitiveDataProcessor implements ProcessorInterface
{
    private const array SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password',
        'token', 'access_token', 'refresh_token', 'api_key', 'secret',
        'authorization', 'card_number', 'cvv', 'cvc',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(context: $this->redact($record->context));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function redact(array $data): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            $redacted[$key] = match (true) {
                is_string($key) && in_array(mb_strtolower($key), self::SENSITIVE_KEYS, true) => '[REDACTED]',
                is_array($value) => $this->redact($value),
                default => $value,
            };
        }

        return $redacted;
    }
}
