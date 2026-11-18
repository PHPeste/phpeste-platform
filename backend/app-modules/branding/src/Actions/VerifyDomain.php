<?php

declare(strict_types=1);

namespace PHPeste\Branding\Actions;

use PHPeste\Branding\Contracts\DnsResolver;
use PHPeste\Branding\Domains\DomainResolver;
use PHPeste\Branding\Models\EditionDomain;

class VerifyDomain
{
    public function __construct(private readonly DnsResolver $dns) {}

    public function handle(EditionDomain $domain): EditionDomain
    {
        if ($domain->isVerified()) {
            return $domain;
        }

        $target = mb_strtolower((string) config('app.public_cname_target'));
        $txtMatches = in_array($domain->txtRecordValue(), $this->dns->txt($domain->txtRecordName()), true);
        $cnameMatches = in_array($target, $this->dns->cname($domain->host), true);

        $domain->update([
            'verified_at' => $txtMatches || $cnameMatches ? now() : null,
            'last_checked_at' => now(),
            'last_check_error' => $txtMatches || $cnameMatches
                ? null
                : "Não encontramos o TXT {$domain->txtRecordName()} nem um CNAME para {$target}. A propagação do DNS pode levar algumas horas.",
        ]);

        DomainResolver::forget($domain->host);

        return $domain;
    }
}
