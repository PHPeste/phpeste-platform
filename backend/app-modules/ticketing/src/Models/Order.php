<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Ticketing\Enums\OrderStatus;

/**
 * @property string $id
 * @property string $edition_id
 * @property int $number
 * @property OrderStatus $status
 * @property string|null $user_id
 * @property string $buyer_name
 * @property string $buyer_email
 * @property string|null $buyer_document
 * @property string|null $buyer_document_hash
 * @property int $subtotal_cents
 * @property int $discount_cents
 * @property int $fee_cents
 * @property int $total_cents
 * @property string|null $coupon_id
 * @property string|null $coupon_code
 * @property string|null $coupon_redemption_id
 * @property string $access_token
 * @property string $idempotency_key
 * @property string $request_hash
 * @property Carbon|null $expires_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $expired_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $refunded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, OrderItem> $items
 */
#[Fillable(['edition_id', 'number', 'status', 'user_id', 'buyer_name', 'buyer_email', 'buyer_document', 'buyer_document_hash', 'subtotal_cents', 'discount_cents', 'fee_cents', 'total_cents', 'coupon_id', 'coupon_code', 'coupon_redemption_id', 'access_token', 'idempotency_key', 'request_hash', 'expires_at', 'paid_at', 'expired_at', 'cancelled_at', 'refunded_at'])]
#[Hidden(['buyer_document', 'buyer_document_hash', 'access_token', 'request_hash'])]
#[Table(name: 'orders')]
class Order extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->oldest()->orderBy('id');
    }

    public function maskedDocument(): ?string
    {
        if ($this->buyer_document === null) {
            return null;
        }

        return '***.***.'.mb_substr($this->buyer_document, 6, 3).'-**';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'number' => 'integer',
            'buyer_document' => 'encrypted',
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'fee_cents' => 'integer',
            'total_cents' => 'integer',
            'access_token' => 'encrypted',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }
}
