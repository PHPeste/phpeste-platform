<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Cfp\Actions\SyncCfpStatus;
use PHPeste\Cfp\Enums\CfpStatus;
use PHPeste\Cfp\Models\CfpSetting;

class CfpSettingsResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = $this->resource;

        if (!$settings instanceof CfpSetting) {
            return [];
        }

        $status = resolve(SyncCfpStatus::class)->status($settings);

        return [
            'status' => $status->value,
            'status_label' => $status->label(),
            'days_until_close' => $status === CfpStatus::Open && $settings->closes_at !== null
                ? (int) ceil(now()->diffInDays($settings->closes_at))
                : null,
            'opens_at' => $this->isoDate($settings->opens_at),
            'closes_at' => $this->isoDate($settings->closes_at),
            'results_at' => $this->isoDate($settings->results_at),
            'anonymous_review' => $settings->anonymous_review,
            'require_nordeste_quota' => $settings->require_nordeste_quota,
            'nordeste_quota_percent' => $settings->nordeste_quota_percent,
            'proposals_per_person' => $settings->proposals_per_person,
        ];
    }
}
