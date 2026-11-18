<?php

declare(strict_types=1);

namespace PHPeste\Audit\Support;

final class AuditRedactor
{
    public const string REMOVED = '[removido]';

    private const array SECRET_KEYS = [
        'password', 'password_confirmation', 'current_password',
        'token', 'access_token', 'refresh_token', 'api_key', 'apikey', 'secret', 'client_secret',
        'private_key', 'webhook_secret', 'authorization', 'credentials', 'card_number', 'cvv', 'cvc',
    ];

    private const array SECRET_SUFFIXES = ['_key', '_secret', '_token', '_password'];

    private const array PERSONAL_KEYS = ['email', 'cpf', 'cnpj', 'document', 'phone', 'telefone', 'celular'];

    /**
     * @param  array<array-key, mixed>|null  $data
     * @return array<array-key, mixed>|null
     */
    public static function redact(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $redacted = [];

        foreach ($data as $key => $value) {
            $name = is_string($key) ? mb_strtolower($key) : '';

            $redacted[$key] = match (true) {
                self::isSecret($name) => self::REMOVED,
                is_array($value) => self::redact($value),
                is_string($value) && self::isPersonal($name) => self::maskPersonal($name, $value),
                default => $value,
            };
        }

        return $redacted;
    }

    private static function isSecret(string $key): bool
    {
        if ($key === '') {
            return false;
        }

        if (in_array($key, self::SECRET_KEYS, true)) {
            return true;
        }

        return array_any(self::SECRET_SUFFIXES, fn (string $suffix): bool => str_ends_with($key, $suffix));
    }

    private static function isPersonal(string $key): bool
    {
        return array_any(self::PERSONAL_KEYS, fn (string $personal): bool => $key === $personal || str_ends_with($key, '_'.$personal));
    }

    private static function maskPersonal(string $key, string $value): string
    {
        if (str_contains($key, 'email') && str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);

            return mb_substr($local, 0, 1).'***@'.$domain;
        }

        $digits = (string) preg_replace('/\D/', '', $value);

        return $digits === '' ? '***' : '***'.mb_substr($digits, -2);
    }
}
