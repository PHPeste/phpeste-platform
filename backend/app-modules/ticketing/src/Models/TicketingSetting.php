<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * @property string $id
 * @property string $edition_id
 * @property int|null $capacity
 * @property int $allocated
 * @property int $reservation_minutes
 * @property int|null $max_tickets_per_buyer
 * @property int $gateway_fee_basis_points
 * @property int $order_sequence
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'capacity', 'allocated', 'reservation_minutes', 'max_tickets_per_buyer', 'gateway_fee_basis_points'])]
#[Table(name: 'ticketing_settings')]
class TicketingSetting extends Model
{
    use BelongsToEdition;
    use HasUuids;

    protected $attributes = ['allocated' => 0, 'reservation_minutes' => 30, 'gateway_fee_basis_points' => 0, 'order_sequence' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'allocated' => 'integer',
            'reservation_minutes' => 'integer',
            'max_tickets_per_buyer' => 'integer',
            'gateway_fee_basis_points' => 'integer',
            'order_sequence' => 'integer',
        ];
    }
}
