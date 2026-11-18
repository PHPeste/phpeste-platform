<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Proposals;

use PHPeste\Cfp\Models\CfpCriterion;
use PHPeste\Cfp\Models\CfpSetting;
use PHPeste\Cfp\Models\CfpTrack;
use PHPeste\Community\Contracts\EditionCloner;

class CfpCloner implements EditionCloner
{
    public function key(): string
    {
        return 'cfp';
    }

    public function label(): string
    {
        return 'CFP: regras, trilhas e critérios';
    }

    public function clone(string $sourceEditionId, string $targetEditionId): void
    {
        $settings = CfpSetting::query()->forEdition($sourceEditionId)->first();

        if ($settings !== null) {
            CfpSetting::create([
                'edition_id' => $targetEditionId,
                'anonymous_review' => $settings->anonymous_review,
                'require_nordeste_quota' => $settings->require_nordeste_quota,
                'nordeste_quota_percent' => $settings->nordeste_quota_percent,
                'proposals_per_person' => $settings->proposals_per_person,
            ]);
        }

        CfpTrack::query()->forEdition($sourceEditionId)->get()->each(fn (CfpTrack $track) => CfpTrack::create([
            'edition_id' => $targetEditionId,
            'name' => $track->name,
            'slots' => $track->slots,
            'position' => $track->position,
        ]));

        CfpCriterion::query()->forEdition($sourceEditionId)->get()->each(fn (CfpCriterion $criterion) => CfpCriterion::create([
            'edition_id' => $targetEditionId,
            'name' => $criterion->name,
            'description' => $criterion->description,
            'weight' => $criterion->weight,
            'position' => $criterion->position,
        ]));
    }
}
