<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Actions;

use PHPeste\Sponsors\Enums\DeliverableStatus;
use PHPeste\Sponsors\Models\SponsorDeliverable;
use PHPeste\Sponsors\Models\Sponsorship;
use PHPeste\Sponsors\Models\SponsorTier;

/**
 * Cria uma entrega para cada contrapartida da cota, na primeira vez que o
 * acordo fecha. Acordo que já tem entregas não ganha outras.
 */
class GenerateDeliverables
{
    public function handle(Sponsorship $sponsorship): int
    {
        if ($sponsorship->sponsor_tier_id === null || SponsorDeliverable::query()->forEdition($sponsorship->edition_id)->where('sponsorship_id', $sponsorship->id)->exists()) {
            return 0;
        }

        $tier = SponsorTier::query()->forEdition($sponsorship->edition_id)->find($sponsorship->sponsor_tier_id);

        if (!$tier instanceof SponsorTier) {
            return 0;
        }

        foreach ($tier->benefits as $position => $benefit) {
            SponsorDeliverable::create([
                'edition_id' => $sponsorship->edition_id,
                'sponsorship_id' => $sponsorship->id,
                'title' => $benefit,
                'owner_id' => $sponsorship->owner_id,
                'status' => DeliverableStatus::Pending,
                'position' => $position,
            ]);
        }

        return count($tier->benefits);
    }
}
