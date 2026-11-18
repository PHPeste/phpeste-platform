<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Community\Models\Edition;

class PublicEditionResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $edition = $this->resource;

        if (!$edition instanceof Edition) {
            return [];
        }

        return [
            'id' => $edition->id,
            'name' => $edition->name,
            'slug' => $edition->slug,
            'status' => $edition->status->value,
            'starts_on' => $edition->starts_on?->toDateString(),
            'ends_on' => $edition->ends_on?->toDateString(),
            'city' => $edition->city,
            'state' => $edition->state?->value,
            'timezone' => $edition->timezone,
            'community' => [
                'name' => $edition->community->name,
                'slug' => $edition->community->slug,
            ],
        ];
    }
}
