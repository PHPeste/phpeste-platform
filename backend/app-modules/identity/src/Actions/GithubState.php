<?php

declare(strict_types=1);

namespace PHPeste\Identity\Actions;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class GithubState
{
    public const string LOGIN = 'login';

    public const string LINK = 'link';

    private const int TTL_SECONDS = 600;

    public static function issue(string $intent, ?string $userId = null): string
    {
        return Crypt::encryptString((string) json_encode([
            'nonce' => Str::random(16),
            'intent' => $intent,
            'user' => $userId,
            'expires' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
        ]));
    }

    /**
     * @return array{intent: string, user: string|null}
     */
    public static function decode(string $state): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($state), true);
        } catch (DecryptException) {
            $payload = null;
        }

        if (
            !is_array($payload)
            || !in_array($payload['intent'] ?? null, [self::LOGIN, self::LINK], true)
            || !is_int($payload['expires'] ?? null)
            || $payload['expires'] < now()->getTimestamp()
        ) {
            throw ValidationException::withMessages([
                'state' => 'A tentativa de entrar com GitHub expirou ou é inválida. Comece de novo.',
            ]);
        }

        $user = $payload['user'] ?? null;

        return ['intent' => (string) $payload['intent'], 'user' => is_string($user) ? $user : null];
    }
}
