<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Sponsors\Models\SponsorTier;

class SponsorTierResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tier = $this->resource;

        if (!$tier instanceof SponsorTier) {
            return [];
        }

        $taken = (int) ($tier->getAttribute('committed_count') ?? 0);

        return [
            'id' => $tier->id,
            'name' => $tier->name,
            'price' => $this->money($tier->price_cents),
            'slots' => $tier->slots,
            'slots_taken' => $taken,
            'slots_left' => $tier->slots === null ? null : max(0, $tier->slots - $taken),
            'benefits' => $tier->benefits,
            'position' => $tier->position,
        ];
    }
}
