<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Ticketing\Enums\TicketStatus;
use PHPeste\Ticketing\Enums\TshirtSize;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $order_id
 * @property string $order_item_id
 * @property string $ticket_type_id
 * @property string $code
 * @property TicketStatus $status
 * @property int $qr_version
 * @property string $holder_name
 * @property string $holder_email
 * @property string|null $holder_user_id
 * @property string|null $badge_name
 * @property string|null $company
 * @property TshirtSize|null $tshirt_size
 * @property string|null $accessibility
 * @property Carbon $issued_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $last_sent_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Order $order
 * @property-read OrderItem $item
 * @property-read TicketType $type
 */
#[Fillable(['edition_id', 'order_id', 'order_item_id', 'ticket_type_id', 'code', 'status', 'qr_version', 'holder_name', 'holder_email', 'holder_user_id', 'badge_name', 'company', 'tshirt_size', 'accessibility', 'issued_at', 'cancelled_at', 'last_sent_at'])]
#[Hidden(['accessibility'])]
#[Table(name: 'tickets')]
class Ticket extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    /**
     * @return HasMany<TicketTransfer, $this>
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(TicketTransfer::class);
    }

    public function isActive(): bool
    {
        return $this->status === TicketStatus::Active;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'tshirt_size' => TshirtSize::class,
            'accessibility' => 'encrypted',
            'qr_version' => 'integer',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }
}
