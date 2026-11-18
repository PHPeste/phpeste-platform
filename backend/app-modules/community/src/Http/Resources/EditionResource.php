<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;

class EditionResource extends ApiResource
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
            'community_id' => $edition->community_id,
            'name' => $edition->name,
            'slug' => $edition->slug,
            'status' => $edition->status->value,
            'status_label' => $edition->status->label(),
            'allowed_transitions' => array_map(fn (EditionStatus $status): string => $status->value, $edition->status->allowedTransitions()),
            'starts_on' => $edition->starts_on?->toDateString(),
            'ends_on' => $edition->ends_on?->toDateString(),
            'year' => $edition->starts_on?->year,
            'city' => $edition->city,
            'state' => $edition->state?->value,
            'timezone' => $edition->timezone,
            'site_url' => null,
            'role' => $this->whenLoaded('members', fn (): ?string => $edition->members->first()?->role->value),
            'created_at' => $this->isoDate($edition->created_at),
        ];
    }
}
