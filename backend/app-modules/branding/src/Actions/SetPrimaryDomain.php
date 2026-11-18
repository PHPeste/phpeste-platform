<?php

declare(strict_types=1);

namespace PHPeste\Branding\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Branding\Domains\DomainResolver;
use PHPeste\Branding\Models\EditionDomain;

class SetPrimaryDomain
{
    public function handle(EditionDomain $domain): EditionDomain
    {
        if (!$domain->isVerified()) {
            throw ValidationException::withMessages([
                'host' => 'Só um domínio verificado pode ser o principal.',
            ]);
        }

        DB::transaction(function () use ($domain): void {
            EditionDomain::query()->where('is_primary', true)->update(['is_primary' => false]);
            $domain->update(['is_primary' => true]);
        });

        EditionDomain::query()->pluck('host')->each(fn (string $host) => DomainResolver::forget($host));

        return $domain;
    }
}
