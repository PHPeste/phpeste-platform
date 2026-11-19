<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Payments\Contracts\PaymentLedger;
use PHPeste\Payments\Data\RefundRecord;
use PHPeste\Payments\Data\RefundRequest;
use PHPeste\Payments\Enums\RefundReason;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\OrderItem;

class RefundOrder
{
    public function __construct(private readonly PaymentLedger $ledger) {}

    /**
     * @param  list<string>|null  $itemIds
     */
    public function handle(Order $order, ?int $amountCents, RefundReason $reason, ?string $note, bool $cancelTickets, ?array $itemIds, ?string $actorId): RefundRecord
    {
        if ($order->total_cents === 0) {
            throw ValidationException::withMessages(['order' => 'Pedido sem pagamento: para tirar o ingresso, cancele o pedido.']);
        }

        $items = $cancelTickets ? $this->items($order, $itemIds) : [];
        $amount = $amountCents ?? ($itemIds !== null && $items !== []
            ? array_sum(array_map(fn (OrderItem $item): int => $item->total_cents, $items))
            : $order->total_cents - $order->refunded_cents);

        if ($amountCents === null && $amount <= 0) {
            throw ValidationException::withMessages(['amount_cents' => 'Este pedido já foi reembolsado por inteiro.']);
        }

        return $this->ledger->refund(new RefundRequest(
            $order->edition_id,
            $order->id,
            $amount,
            $reason,
            $note,
            $actorId,
            ['item_ids' => array_map(fn (OrderItem $item): string => $item->id, $items)],
        ));
    }

    /**
     * @param  list<string>|null  $itemIds
     * @return list<OrderItem>
     */
    private function items(Order $order, ?array $itemIds): array
    {
        if (!in_array($order->status, [OrderStatus::Paid, OrderStatus::Refunded], true)) {
            throw ValidationException::withMessages(['cancel_tickets' => sprintf('Os ingressos de um pedido %s já não valem; reembolse sem cancelar ingressos.', mb_strtolower($order->status->label()))]);
        }

        $active = OrderItem::query()->forEdition($order->edition_id)
            ->where('order_id', $order->id)
            ->whereNull('cancelled_at')
            ->when($itemIds !== null, fn ($query) => $query->whereIn('id', $itemIds ?? []))
            ->get();

        if ($active->isEmpty() || ($itemIds !== null && $active->count() !== count(array_unique($itemIds)))) {
            throw ValidationException::withMessages(['item_ids' => 'Escolha ingressos deste pedido que ainda não foram cancelados.']);
        }

        return array_values($active->all());
    }
}
