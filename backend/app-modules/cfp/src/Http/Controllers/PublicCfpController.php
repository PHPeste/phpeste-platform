<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use PHPeste\Cfp\Actions\SyncCfpStatus;
use PHPeste\Cfp\Data\ProposalForm;
use PHPeste\Cfp\Models\CfpSetting;
use PHPeste\Cfp\Models\CfpTrack;
use PHPeste\Community\Contracts\EditionDirectory;

class PublicCfpController
{
    public function __invoke(string $edition, EditionDirectory $editions, SyncCfpStatus $sync): JsonResponse
    {
        abort_unless($editions->isPublished($edition), 404);

        $settings = CfpSetting::query()->forEdition($edition)->first();

        abort_if($settings === null, 404);

        $status = $sync->status($settings);

        return response()->json([
            'data' => [
                'edition_id' => $edition,
                'status' => $status->value,
                'status_label' => $status->label(),
                'opens_at' => $settings->opens_at?->clone()->utc()->toIso8601ZuluString(),
                'closes_at' => $settings->closes_at?->clone()->utc()->toIso8601ZuluString(),
                'results_at' => $settings->results_at?->clone()->utc()->toIso8601ZuluString(),
                'anonymous_review' => $settings->anonymous_review,
                'proposals_per_person' => $settings->proposals_per_person,
                'tracks' => CfpTrack::query()->forEdition($edition)->orderBy('position')->get()
                    ->map(fn (CfpTrack $track): array => ['id' => $track->id, 'name' => $track->name])
                    ->values()
                    ->all(),
                'form' => ProposalForm::options(),
            ],
        ]);
    }
}
