<?php

declare(strict_types=1);

namespace PHPeste\Branding\Domains;

use PHPeste\Branding\Contracts\EditionSites;
use PHPeste\Branding\Models\EditionDomain;

class PrimaryDomainSites implements EditionSites
{
    public function url(string $editionId): string
    {
        $host = EditionDomain::query()
            ->forEdition($editionId)
            ->whereNotNull('verified_at')
            ->orderByDesc('is_primary')
            ->oldest()
            ->value('host');

        return is_string($host) ? "https://{$host}" : mb_rtrim((string) config('app.admin_url'), '/');
    }
}
