<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Webhooks;

/**
 * Assinatura dos webhooks do Resend (padrão Svix): HMAC-SHA256 em base64 de
 * "{svix-id}.{svix-timestamp}.{corpo}", com o segredo `whsec_...` decodificado.
 * O cabeçalho pode trazer várias assinaturas ("v1,abc v1,def"), e o
 * timestamp precisa estar a até 5 minutos de agora (contra replay).
 */
final readonly class SvixSignature
{
    public const int TOLERANCE_SECONDS = 300;

    public function __construct(private string $secret) {}

    public function valid(?string $id, ?string $timestamp, ?string $signatures, string $body, ?int $now = null): bool
    {
        if ($this->secret === '' || $id === null || $timestamp === null || $signatures === null || !ctype_digit($timestamp)) {
            return false;
        }

        if (abs(($now ?? now()->getTimestamp()) - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = $this->sign($id, $timestamp, $body);

        foreach (explode(' ', $signatures) as $candidate) {
            [$version, $signature] = array_pad(explode(',', $candidate, 2), 2, '');

            if ($version === 'v1' && hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function sign(string $id, string $timestamp, string $body): string
    {
        $key = base64_decode(str_starts_with($this->secret, 'whsec_') ? mb_substr($this->secret, 6) : $this->secret, true);

        return base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $key === false ? '' : $key, true));
    }
}
