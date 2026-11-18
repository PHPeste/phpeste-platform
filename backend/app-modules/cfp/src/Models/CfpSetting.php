<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Cfp\Enums\CfpStatus;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * @property string $id
 * @property string $edition_id
 * @property Carbon|null $opens_at
 * @property Carbon|null $closes_at
 * @property Carbon|null $results_at
 * @property bool $anonymous_review
 * @property bool $require_nordeste_quota
 * @property int $nordeste_quota_percent
 * @property int $proposals_per_person
 * @property CfpStatus $announced_status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'opens_at', 'closes_at', 'results_at', 'anonymous_review', 'require_nordeste_quota', 'nordeste_quota_percent', 'proposals_per_person', 'announced_status'])]
#[Table(name: 'cfp_settings')]
class CfpSetting extends Model
{
    use BelongsToEdition;
    use HasUuids;

    protected $attributes = [
        'anonymous_review' => true,
        'require_nordeste_quota' => true,
        'nordeste_quota_percent' => 50,
        'proposals_per_person' => 3,
        'announced_status' => 'draft',
    ];

    public function statusFor(bool $editionInCfp, ?Carbon $now = null): CfpStatus
    {
        $now ??= now();

        if ($this->opens_at === null || $this->closes_at === null) {
            return CfpStatus::Draft;
        }

        if ($now->lt($this->opens_at)) {
            return $editionInCfp ? CfpStatus::Scheduled : CfpStatus::Draft;
        }

        if ($now->gte($this->closes_at)) {
            return CfpStatus::Closed;
        }

        return $editionInCfp ? CfpStatus::Open : CfpStatus::Draft;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'results_at' => 'datetime',
            'anonymous_review' => 'boolean',
            'require_nordeste_quota' => 'boolean',
            'nordeste_quota_percent' => 'integer',
            'proposals_per_person' => 'integer',
            'announced_status' => CfpStatus::class,
        ];
    }
}
