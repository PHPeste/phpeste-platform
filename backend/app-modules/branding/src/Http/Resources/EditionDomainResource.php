<?php

declare(strict_types=1);

namespace PHPeste\Branding\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Branding\Models\EditionDomain;

class EditionDomainResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $domain = $this->resource;

        if (!$domain instanceof EditionDomain) {
            return [];
        }

        return [
            'id' => $domain->id,
            'host' => $domain->host,
            'is_platform' => $domain->is_platform,
            'is_primary' => $domain->is_primary,
            'verified' => $domain->isVerified(),
            'verified_at' => $this->isoDate($domain->verified_at),
            'verification' => $domain->isVerified() ? null : [
                'txt' => ['name' => $domain->txtRecordName(), 'value' => $domain->txtRecordValue()],
                'cname' => ['name' => $domain->host, 'value' => (string) config('app.public_cname_target')],
            ],
            'last_checked_at' => $this->isoDate($domain->last_checked_at),
            'last_check_error' => $domain->last_check_error,
            'url' => "https://{$domain->host}",
        ];
    }
}
