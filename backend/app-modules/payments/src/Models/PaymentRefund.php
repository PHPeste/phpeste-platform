<?php

declare(strict_types=1);

namespace PHPeste\Payments\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Payments\Data\RefundRecord;
use PHPeste\Payments\Enums\RefundReason;
use PHPeste\Payments\Enums\RefundStatus;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $payment_charge_id
 * @property string $payment_account_id
 * @property string $reference
 * @property string|null $gateway_refund_id
 * @property int $amount_cents
 * @property RefundStatus $status
 * @property RefundReason $reason
 * @property string|null $note
 * @property array<string, mixed>|null $metadata
 * @property string|null $failure_reason
 * @property string|null $requested_by
 * @property string|null $settled_by
 * @property Carbon|null $accepted_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'payment_charge_id', 'payment_account_id', 'reference', 'gateway_refund_id', 'amount_cents', 'status', 'reason', 'note', 'metadata', 'failure_reason', 'requested_by', 'settled_by', 'accepted_at', 'completed_at'])]
#[Table(name: 'payment_refunds')]
class PaymentRefund extends Model
{
    use BelongsToEdition;
    use HasUuids;

    public function toRecord(): RefundRecord
    {
        return new RefundRecord(
            $this->id,
            $this->reference,
            $this->payment_charge_id,
            $this->amount_cents,
            $this->status,
            $this->reason,
            $this->note,
            $this->metadata ?? [],
            $this->failure_reason,
            $this->requested_by,
            $this->created_at,
            $this->completed_at,
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'status' => RefundStatus::class,
            'reason' => RefundReason::class,
            'metadata' => 'array',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
