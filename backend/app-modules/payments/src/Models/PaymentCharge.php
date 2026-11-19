<?php

declare(strict_types=1);

namespace PHPeste\Payments\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Payments\Data\PaymentAttempt;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Enums\PaymentMethod;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $payment_account_id
 * @property string $reference
 * @property string $driver
 * @property PaymentMethod $method
 * @property string $gateway_charge_id
 * @property ChargeStatus $status
 * @property int $amount_cents
 * @property int|null $fee_cents
 * @property int $refunded_cents
 * @property int $installments
 * @property string|null $pix_copy_paste
 * @property string|null $pix_qr_code
 * @property string|null $checkout_url
 * @property Carbon|null $expires_at
 * @property Carbon|null $paid_at
 * @property string|null $failure_reason
 * @property Carbon|null $checked_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PaymentAccount $account
 */
#[Fillable(['edition_id', 'payment_account_id', 'reference', 'driver', 'method', 'gateway_charge_id', 'status', 'amount_cents', 'fee_cents', 'refunded_cents', 'installments', 'pix_copy_paste', 'pix_qr_code', 'checkout_url', 'expires_at', 'paid_at', 'failure_reason', 'checked_at'])]
#[Table(name: 'payment_charges')]
class PaymentCharge extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return BelongsTo<PaymentAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function toAttempt(): PaymentAttempt
    {
        return new PaymentAttempt(
            $this->id,
            $this->method,
            $this->status,
            $this->amount_cents,
            $this->installments,
            $this->pix_copy_paste,
            $this->pix_qr_code,
            $this->checkout_url,
            $this->expires_at,
            $this->paid_at,
            $this->failure_reason,
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => ChargeStatus::class,
            'amount_cents' => 'integer',
            'fee_cents' => 'integer',
            'refunded_cents' => 'integer',
            'installments' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }
}
