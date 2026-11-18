<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Community\Contracts\EditionPrimaryHosts;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Enums\SiteMode;
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
            'community' => $this->whenLoaded('community', fn (): array => [
                'id' => $edition->community->id,
                'name' => $edition->community->name,
                'slug' => $edition->community->slug,
            ]),
            'name' => $edition->name,
            'slug' => $edition->slug,
            'status' => $edition->status->value,
            'status_label' => $edition->status->label(),
            'allowed_transitions' => array_map(fn (EditionStatus $status): string => $status->value, $edition->status->allowedTransitions()),
            'status_options' => array_map(fn (EditionStatus $status): array => ['status' => $status->value, 'label' => $status->label(), ...$status->effects()], $edition->status->allowedTransitions()),
            'starts_on' => $edition->starts_on?->toDateString(),
            'ends_on' => $edition->ends_on?->toDateString(),
            'year' => $edition->starts_on?->year,
            'city' => $edition->city,
            'state' => $edition->state?->value,
            'timezone' => $edition->timezone,
            'site_mode' => $edition->site_mode->value,
            'external_site_url' => $edition->external_site_url,
            'site_url' => $edition->site_mode === SiteMode::External ? $edition->external_site_url : $this->primarySite($edition->id),
            'privacy_contact_email' => $edition->privacy_contact_email,
            'privacy_policy_url' => $edition->privacy_policy_url,
            'anonymize_after_event' => $edition->anonymize_after_event,
            'role' => $this->whenLoaded('members', fn (): ?string => $edition->members->first()?->role->value),
            'created_at' => $this->isoDate($edition->created_at),
        ];
    }

    private function primarySite(string $editionId): ?string
    {
        $host = resolve(EditionPrimaryHosts::class)->primaryHost($editionId);

        return $host === null ? null : "https://{$host}";
    }
}
