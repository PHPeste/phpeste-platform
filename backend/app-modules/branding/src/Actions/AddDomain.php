<?php

declare(strict_types=1);

namespace PHPeste\Branding\Actions;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPeste\Branding\Domains\DomainResolver;
use PHPeste\Branding\Domains\Hostname;
use PHPeste\Branding\Models\EditionDomain;

class AddDomain
{
    public function handle(string $host): EditionDomain
    {
        $isPlatform = Hostname::isPlatform($host);

        if ($isPlatform) {
            $label = (string) Hostname::platformLabel($host);

            if (str_contains($label, '.') || in_array($label, Hostname::RESERVED_LABELS, true)) {
                throw ValidationException::withMessages([
                    'host' => sprintf('Use um subdomínio simples de %s que não seja reservado (ex.: 2027-php-pb.%s).', Hostname::baseDomain(), Hostname::baseDomain()),
                ]);
            }
        }

        if (EditionDomain::query()->withoutGlobalScopes()->where('host', $host)->exists()) {
            throw ValidationException::withMessages([
                'host' => 'Este domínio já está cadastrado.',
            ]);
        }

        $domain = EditionDomain::create([
            'host' => $host,
            'is_platform' => $isPlatform,
            'is_primary' => !EditionDomain::query()->exists(),
            'verification_token' => Str::random(32),
            'verified_at' => $isPlatform ? now() : null,
        ]);

        DomainResolver::forget($host);

        return $domain;
    }
}
