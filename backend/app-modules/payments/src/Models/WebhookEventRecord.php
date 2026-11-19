<?php

declare(strict_types=1);

namespace PHPeste\Payments\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Payments\Enums\WebhookEventStatus;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $payment_account_id
 * @property string $driver
 * @property string $event_id
 * @property string $type
 * @property string|null $gateway_charge_id
 * @property WebhookEventStatus $status
 * @property string $payload
 * @property string|null $result
 * @property int $attempts
 * @property Carbon|null $next_attempt_at
 * @property Carbon|null $processing_started_at
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'payment_account_id', 'driver', 'event_id', 'type', 'gateway_charge_id', 'status', 'payload', 'result', 'attempts', 'next_attempt_at', 'processing_started_at', 'processed_at'])]
#[Hidden(['payload'])]
#[Table(name: 'webhook_events')]
class WebhookEventRecord extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WebhookEventStatus::class,
            'payload' => 'encrypted',
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
