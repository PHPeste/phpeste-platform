<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Sponsors\Enums\SponsorshipStage;

/**
 * Acordo de patrocínio: um card do funil.
 *
 * @property string $id
 * @property string $edition_id
 * @property string $sponsor_id
 * @property string|null $sponsor_tier_id
 * @property SponsorshipStage $stage
 * @property int $value_cents
 * @property string|null $owner_id
 * @property string|null $lost_reason
 * @property int $position
 * @property Carbon|null $closed_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $lost_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Sponsor $sponsor
 * @property-read SponsorTier|null $tier
 * @property-read Collection<int, SponsorshipPayment> $payments
 * @property-read Collection<int, SponsorDeliverable> $deliverables
 */
#[Fillable(['edition_id', 'sponsor_id', 'sponsor_tier_id', 'stage', 'value_cents', 'owner_id', 'lost_reason', 'position', 'closed_at', 'paid_at', 'lost_at'])]
#[Table(name: 'sponsorships')]
class Sponsorship extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return BelongsTo<Sponsor, $this>
     */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    /**
     * @return BelongsTo<SponsorTier, $this>
     */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(SponsorTier::class, 'sponsor_tier_id');
    }

    /**
     * @return HasMany<SponsorshipPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SponsorshipPayment::class)->oldest('paid_on')->orderBy('id');
    }

    /**
     * @return HasMany<SponsorDeliverable, $this>
     */
    public function deliverables(): HasMany
    {
        return $this->hasMany(SponsorDeliverable::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => SponsorshipStage::class,
            'value_cents' => 'integer',
            'position' => 'integer',
            'closed_at' => 'datetime',
            'paid_at' => 'datetime',
            'lost_at' => 'datetime',
        ];
    }
}
