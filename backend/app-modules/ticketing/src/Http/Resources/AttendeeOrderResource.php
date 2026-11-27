<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Payments\Data\RefundRecord;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\OrderItem;

class AttendeeOrderResource extends ApiResource
{
    /**
     * @var list<RefundRecord>
     */
    public array $refunds = [];

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
            'subtotal' => $this->money($order->subtotal_cents),
            'discount' => $this->money($order->discount_cents),
            'fee' => $this->money($order->fee_cents),
            'total' => $this->money($order->total_cents),
            'refunded' => $this->money($order->refunded_cents),
            'coupon_code' => $order->coupon_code,
            'created_at' => $this->isoDate($order->created_at),
            'paid_at' => $this->isoDate($order->paid_at),
            'expires_at' => $this->isoDate($order->expires_at),
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'id' => $item->id,
                'ticket_type_name' => $item->ticket_type_name,
                'batch_name' => $item->batch_name,
                'attendee_name' => $item->attendee_name,
                'total' => $this->money($item->total_cents),
                'cancelled_at' => $this->isoDate($item->cancelled_at),
            ])->all(),
            'refunds' => array_map(fn (RefundRecord $refund): array => [
                'id' => $refund->id,
                'amount' => $this->money($refund->amountCents),
                'status' => $refund->status->value,
                'status_label' => $refund->status->label(),
                'reason_label' => $refund->reason->label(),
                'created_at' => $this->isoDate($refund->createdAt),
                'completed_at' => $this->isoDate($refund->completedAt),
            ], $this->refunds),
        ];
    }
}
