<?php

declare(strict_types=1);

namespace PHPeste\Branding\Domains;

final class Hostname
{
    public const array RESERVED_LABELS = ['www', 'api', 'admin', 'app', 'painel', 'mail', 'email', 'status', 'docs', 'cdn', 'static', 'edicoes'];

    public static function normalize(string $host): ?string
    {
        $host = mb_strtolower(mb_trim($host));
        $host = (string) preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $host);
        $host = explode('/', $host, 2)[0];
        $host = (string) preg_replace('/:\d+$/', '', $host);
        $host = mb_rtrim($host, '.');

        if ($host === '') {
            return null;
        }

        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', $host) === 1) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            $host = is_string($ascii) ? $ascii : '';
        }

        $label = '(?!-)[a-z0-9-]{1,63}(?<!-)';

        if (mb_strlen($host) > 253 || preg_match("/^(?:{$label}\\.)+[a-z][a-z0-9-]{1,62}$/", $host) !== 1) {
            return null;
        }

        return $host;
    }

    public static function baseDomain(): string
    {
        return mb_strtolower((string) config('app.public_base_domain'));
    }

    public static function isPlatform(string $host): bool
    {
        return str_ends_with($host, '.'.self::baseDomain());
    }

    public static function platformLabel(string $host): ?string
    {
        if (!self::isPlatform($host)) {
            return null;
        }

        return mb_substr($host, 0, -mb_strlen('.'.self::baseDomain()));
    }
}
