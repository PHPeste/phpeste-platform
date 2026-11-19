<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\OrderItem;

class OrderResource extends ApiResource
{
    public bool $withToken = false;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->resource;

        if (!$order instanceof Order) {
            return [];
        }

        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'source' => $order->source->value,
            'external' => $order->external() ? ['platform' => $order->external_platform, 'reference' => $order->external_reference] : null,
            'buyer' => ['name' => $order->buyer_name, 'email' => $order->buyer_email, 'document' => $order->maskedDocument()],
            'subtotal' => $this->money($order->subtotal_cents),
            'discount' => $this->money($order->discount_cents),
            'fee' => $this->money($order->fee_cents),
            'total' => $this->money($order->total_cents),
            'refunded' => $this->money($order->refunded_cents),
            'gateway_fee' => $this->money($order->gateway_fee_cents),
            'requires_payment' => $order->total_cents > 0,
            'coupon_code' => $order->coupon_code,
            'payment_account_id' => $order->payment_account_id,
            'expires_at' => $this->isoDate($order->expires_at),
            'paid_at' => $this->isoDate($order->paid_at),
            'created_at' => $this->isoDate($order->created_at),
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'id' => $item->id,
                'ticket_type_id' => $item->ticket_type_id,
                'ticket_type_name' => $item->ticket_type_name,
                'batch_name' => $item->batch_name,
                'unit_price' => $this->money($item->unit_price_cents),
                'discount' => $this->money($item->discount_cents),
                'fee' => $this->money($item->fee_cents),
                'total' => $this->money($item->total_cents),
                'attendee' => ['name' => $item->attendee_name, 'email' => $item->attendee_email],
                'answers' => $item->answers ?? (object) [],
                'cancelled_at' => $this->isoDate($item->cancelled_at),
                'external_code' => $item->external_code,
            ])->all(),
            ...($this->withToken ? ['access_token' => $order->access_token] : []),
        ];
    }
}
