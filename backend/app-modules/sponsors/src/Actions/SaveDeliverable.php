<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Actions;

use Illuminate\Support\Carbon;
use PHPeste\Sponsors\Enums\DeliverableStatus;
use PHPeste\Sponsors\Models\SponsorDeliverable;
use PHPeste\Sponsors\Models\Sponsorship;

class SaveDeliverable
{
    public function __construct(private readonly EnsureTeamMember $team) {}

    /**
     * @param  array{title?: string, owner_id?: string|null, due_on?: Carbon|null, status?: DeliverableStatus}  $attributes
     */
    public function handle(Sponsorship $sponsorship, ?SponsorDeliverable $deliverable, array $attributes): SponsorDeliverable
    {
        if (array_key_exists('owner_id', $attributes)) {
            $this->team->handle($sponsorship->edition_id, $attributes['owner_id'], 'owner_id');
        }

        if (isset($attributes['status'])) {
            $attributes['done_at'] = $attributes['status'] === DeliverableStatus::Done ? ($deliverable->done_at ?? now()) : null;
        }

        if (!$deliverable instanceof SponsorDeliverable) {
            return SponsorDeliverable::create([
                'edition_id' => $sponsorship->edition_id,
                'sponsorship_id' => $sponsorship->id,
                'status' => DeliverableStatus::Pending,
                'position' => (int) SponsorDeliverable::query()->forEdition($sponsorship->edition_id)->where('sponsorship_id', $sponsorship->id)->max('position') + 1,
                ...$attributes,
            ]);
        }

        $deliverable->update($attributes);

        return $deliverable;
    }
}
