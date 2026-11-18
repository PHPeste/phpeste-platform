<?php

declare(strict_types=1);

namespace PHPeste\Branding\Domains;

use Illuminate\Support\Facades\Cache;
use PHPeste\Branding\Models\EditionDomain;
use PHPeste\Community\Contracts\EditionDirectory;

class DomainResolver
{
    public const int CACHE_SECONDS = 60;

    public function __construct(private readonly EditionDirectory $editions) {}

    public static function forget(string $host): void
    {
        Cache::forget(self::cacheKey($host));
    }

    /**
     * @return array{edition_id: string, edition_name: string, host: string, primary_host: string}|null
     */
    public function resolve(string $host): ?array
    {
        $cached = Cache::remember(self::cacheKey($host), self::CACHE_SECONDS, fn (): array => ['match' => $this->lookup($host)]);

        return $cached['match'];
    }

    private static function cacheKey(string $host): string
    {
        return 'branding:host:'.sha1($host);
    }

    /**
     * @return array{edition_id: string, edition_name: string, host: string, primary_host: string}|null
     */
    private function lookup(string $host): ?array
    {
        $domain = EditionDomain::query()
            ->withoutGlobalScopes()
            ->where('host', $host)
            ->whereNotNull('verified_at')
            ->first();

        if ($domain === null || !$this->editions->isPublished($domain->edition_id)) {
            return null;
        }

        $primary = EditionDomain::query()
            ->forEdition($domain->edition_id)
            ->whereNotNull('verified_at')
            ->where('is_primary', true)
            ->value('host');

        return [
            'edition_id' => $domain->edition_id,
            'edition_name' => $this->editions->summary($domain->edition_id)['name'] ?? '',
            'host' => $domain->host,
            'primary_host' => is_string($primary) ? $primary : $domain->host,
        ];
    }
}
