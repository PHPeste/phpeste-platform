<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Ticketing\Enums\ReconciliationKind;

/**
 * @property string $id
 * @property string $edition_id
 * @property ReconciliationKind $kind
 * @property string $fingerprint
 * @property string|null $order_id
 * @property string|null $payment_charge_id
 * @property array<string, mixed> $details
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $resolved_at
 * @property string|null $resolved_by
 * @property string|null $resolution
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'kind', 'fingerprint', 'order_id', 'payment_charge_id', 'details', 'first_seen_at', 'last_seen_at', 'resolved_at', 'resolved_by', 'resolution'])]
#[Table(name: 'reconciliation_issues')]
class ReconciliationIssue extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ReconciliationKind::class,
            'details' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
