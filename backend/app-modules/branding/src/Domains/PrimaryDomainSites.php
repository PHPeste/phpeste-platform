<?php

declare(strict_types=1);

namespace PHPeste\Branding\Domains;

use PHPeste\Branding\Contracts\EditionSites;
use PHPeste\Branding\Models\EditionDomain;
use PHPeste\Community\Contracts\EditionPrimaryHosts;

class PrimaryDomainSites implements EditionPrimaryHosts, EditionSites
{
    public function url(string $editionId): string
    {
        $host = $this->primaryHost($editionId);

        return $host !== null ? "https://{$host}" : mb_rtrim((string) config('app.admin_url'), '/');
    }

    public function primaryHost(string $editionId): ?string
    {
        $host = EditionDomain::query()
            ->forEdition($editionId)
            ->whereNotNull('verified_at')
            ->orderByDesc('is_primary')
            ->oldest()
            ->value('host');

        return is_string($host) ? $host : null;
    }
}
