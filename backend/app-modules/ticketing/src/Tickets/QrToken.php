<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Tickets;

use RuntimeException;

final readonly class QrToken
{
    public const string PREFIX = 'PHT1';

    public function __construct(private string $key) {}

    public static function fromConfig(): self
    {
        $key = config('ticketing.qr_key');
        $appKey = config('app.key');

        if (!is_string($key) || $key === '') {
            throw_unless(is_string($appKey) && $appKey !== '', RuntimeException::class, 'Defina TICKETING_QR_KEY ou APP_KEY para assinar os QR dos ingressos.');
            $key = hash_hmac('sha256', 'ticketing.qr', $appKey);
        }

        return new self($key);
    }

    public function make(string $ticketId, int $version): string
    {
        $id = str_replace('-', '', $ticketId);

        return sprintf('%s.%s.%d.%s', self::PREFIX, $id, $version, $this->signature($id, $version));
    }

    /**
     * @return array{ticket_id: string, version: int}|null
     */
    public function read(string $token): ?array
    {
        if (preg_match('/^'.self::PREFIX.'\.([0-9a-f]{32})\.([1-9]\d{0,8})\.([A-Za-z0-9_-]{27})$/', mb_trim($token), $matches) !== 1) {
            return null;
        }

        [, $id, $version, $signature] = $matches;

        if (!hash_equals($this->signature($id, (int) $version), $signature)) {
            return null;
        }

        return [
            'ticket_id' => sprintf('%s-%s-%s-%s-%s', mb_substr($id, 0, 8), mb_substr($id, 8, 4), mb_substr($id, 12, 4), mb_substr($id, 16, 4), mb_substr($id, 20)),
            'version' => (int) $version,
        ];
    }

    private function signature(string $id, int $version): string
    {
        return mb_rtrim(strtr(base64_encode(mb_substr(hash_hmac('sha256', "ticket:{$id}:{$version}", $this->key, true), 0, 20, '8bit')), '+/', '-_'), '=');
    }
}
