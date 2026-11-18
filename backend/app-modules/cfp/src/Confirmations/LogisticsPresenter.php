<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Confirmations;

use PHPeste\Cfp\Models\SpeakerLogistics;
use PHPeste\Identity\Contracts\SpeakerProfiles;

class LogisticsPresenter
{
    public function __construct(private readonly SpeakerProfiles $profiles) {}

    /**
     * @return array<string, mixed>
     */
    public function present(SpeakerLogistics $logistics, bool $forOrganization): array
    {
        $data = [
            'user_id' => $logistics->user_id,
            'origin_city' => $logistics->origin_city,
            'origin_state' => $logistics->origin_state?->value,
            'needs_travel' => $logistics->needs_travel,
            'needs_lodging' => $logistics->needs_lodging,
            'travel_paid_by_organization' => $logistics->travel_paid_by_organization,
            'lodging_paid_by_organization' => $logistics->lodging_paid_by_organization,
        ];

        if ($forOrganization) {
            $data['organization_notes'] = $logistics->organization_notes;
            $data['restrictions'] = $this->profiles->privateDetails($logistics->user_id);
        }

        return $data;
    }
}
