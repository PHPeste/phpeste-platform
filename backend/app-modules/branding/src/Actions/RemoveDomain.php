<?php

declare(strict_types=1);

namespace PHPeste\Branding\Actions;

use Illuminate\Support\Facades\DB;
use PHPeste\Branding\Domains\DomainResolver;
use PHPeste\Branding\Models\EditionDomain;

class RemoveDomain
{
    public function handle(EditionDomain $domain): void
    {
        DB::transaction(function () use ($domain): void {
            $domain->delete();

            if ($domain->is_primary) {
                EditionDomain::query()->whereNotNull('verified_at')->oldest()->first()?->update(['is_primary' => true]);
            }
        });

        EditionDomain::query()->pluck('host')->push($domain->host)->each(fn (string $host) => DomainResolver::forget($host));
    }
}
