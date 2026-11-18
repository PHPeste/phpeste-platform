<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Enums\SiteMode;
use PHPeste\Community\Models\Edition;
use Symfony\Component\HttpFoundation\Response;

class AllowEditionSiteOrigins
{
    public const string CACHE_KEY = 'community:edition-site-origins';

    public const int CACHE_SECONDS = 300;

    /**
     * @return list<string>
     */
    public static function origins(): array
    {
        /** @var list<string> */
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => array_values(array_unique(array_filter(
            Edition::query()
                ->where('site_mode', SiteMode::External)
                ->where('status', '!=', EditionStatus::Draft)
                ->whereNotNull('external_site_url')
                ->pluck('external_site_url')
                ->map(fn (mixed $url): ?string => self::origin((string) $url))
                ->all(),
        ))));
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || !isset($parts['host'])) {
            return null;
        }

        return mb_strtolower("https://{$parts['host']}".(isset($parts['port']) ? ":{$parts['port']}" : ''));
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if (!is_string($origin) || !$request->is('api/v1/public/*') || !in_array(mb_strtolower($origin), self::origins(), true)) {
            return $next($request);
        }

        /** @var list<string> $allowed */
        $allowed = (array) config('cors.allowed_origins', []);
        config(['cors.allowed_origins' => [...$allowed, $origin]]);

        try {
            return $next($request);
        } finally {
            config(['cors.allowed_origins' => $allowed]);
        }
    }
}
