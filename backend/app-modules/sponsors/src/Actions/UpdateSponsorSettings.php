<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Actions;

use PHPeste\Sponsors\Models\SponsorSetting;

class UpdateSponsorSettings
{
    public function handle(string $editionId, ?int $goalCents): SponsorSetting
    {
        $settings = SponsorSetting::query()->forEdition($editionId)->firstOrCreate(['edition_id' => $editionId]);
        $settings->update(['goal_cents' => $goalCents]);

        return $settings;
    }
}
