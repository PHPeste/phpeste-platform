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

/**
 * @property string $id
 * @property string $edition_id
 * @property string $order_id
 * @property string $ticket_type_id
 * @property string $ticket_batch_id
 * @property string $ticket_type_name
 * @property string $batch_name
 * @property int $unit_price_cents
 * @property int $discount_cents
 * @property int $fee_cents
 * @property int $total_cents
 * @property string $attendee_name
 * @property string $attendee_email
 * @property array<string, string|bool>|null $answers
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Order $order
 */
#[Fillable(['edition_id', 'order_id', 'ticket_type_id', 'ticket_batch_id', 'ticket_type_name', 'batch_name', 'unit_price_cents', 'discount_cents', 'fee_cents', 'total_cents', 'attendee_name', 'attendee_email', 'answers'])]
#[Table(name: 'order_items')]
class OrderItem extends Model
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price_cents' => 'integer',
            'discount_cents' => 'integer',
            'fee_cents' => 'integer',
            'total_cents' => 'integer',
            'answers' => 'array',
        ];
    }
}
