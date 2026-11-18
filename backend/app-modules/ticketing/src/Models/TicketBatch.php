<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Ticketing\Enums\BatchStatus;
use PHPeste\Ticketing\Enums\SellOutBehavior;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $ticket_type_id
 * @property string $name
 * @property int $position
 * @property int $price_cents
 * @property int $quantity
 * @property int $allocated
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property SellOutBehavior $on_sell_out
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read TicketType $ticketType
 */
#[Fillable(['edition_id', 'ticket_type_id', 'name', 'position', 'price_cents', 'quantity', 'allocated', 'starts_at', 'ends_at', 'on_sell_out', 'opened_at', 'closed_at'])]
#[Table(name: 'ticket_batches')]
class TicketBatch extends Model
{
    use BelongsToEdition;
    use HasUuids;

    protected $attributes = ['allocated' => 0, 'on_sell_out' => 'next', 'position' => 0];

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function remaining(): int
    {
        return max(0, $this->quantity - $this->allocated);
    }

    public function opensAt(): ?Carbon
    {
        return $this->opened_at ?? $this->starts_at;
    }

    public function status(?Carbon $at = null): BatchStatus
    {
        $at ??= now();

        if ($this->remaining() === 0) {
            return BatchStatus::SoldOut;
        }

        if ($this->closed_at !== null || ($this->ends_at !== null && $this->ends_at->lte($at))) {
            return BatchStatus::Ended;
        }

        $opensAt = $this->opensAt();

        return $opensAt instanceof Carbon && $opensAt->gt($at) ? BatchStatus::Scheduled : BatchStatus::OnSale;
    }

    public function isOpen(?Carbon $at = null): bool
    {
        return $this->status($at) === BatchStatus::OnSale;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'price_cents' => 'integer',
            'quantity' => 'integer',
            'allocated' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'on_sell_out' => SellOutBehavior::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
