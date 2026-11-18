<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Ticketing\Enums\CouponKind;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $code
 * @property string|null $description
 * @property CouponKind $kind
 * @property int $value
 * @property int|null $max_uses
 * @property int $used
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property list<string>|null $ticket_type_ids
 * @property bool $active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'code', 'description', 'kind', 'value', 'max_uses', 'used', 'starts_at', 'ends_at', 'ticket_type_ids', 'active'])]
#[Table(name: 'coupons')]
class Coupon extends Model
{
    use BelongsToEdition;
    use HasUuids;

    protected $attributes = ['used' => 0, 'active' => true];

    public static function normalize(string $code): string
    {
        return mb_strtoupper(mb_trim($code));
    }

    /**
     * @return HasMany<CouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function remaining(): ?int
    {
        return $this->max_uses === null ? null : max(0, $this->max_uses - $this->used);
    }

    public function appliesTo(string $ticketTypeId): bool
    {
        return $this->ticket_type_ids === null || $this->ticket_type_ids === [] || in_array($ticketTypeId, $this->ticket_type_ids, true);
    }

    public function status(?Carbon $at = null): string
    {
        $at ??= now();

        return match (true) {
            !$this->active => 'paused',
            $this->remaining() === 0 => 'exhausted',
            $this->ends_at !== null && $this->ends_at->lte($at) => 'expired',
            $this->starts_at !== null && $this->starts_at->gt($at) => 'scheduled',
            default => 'active',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CouponKind::class,
            'value' => 'integer',
            'max_uses' => 'integer',
            'used' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'ticket_type_ids' => 'array',
            'active' => 'boolean',
        ];
    }
}
