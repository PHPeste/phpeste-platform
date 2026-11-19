<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Listeners;

use Illuminate\Support\Facades\DB;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Notifications\Contracts\EditionMailer;
use PHPeste\Notifications\Enums\EmailTemplate;
use PHPeste\Payments\Events\RefundAccepted;
use PHPeste\Ticketing\Actions\ReleaseTickets;
use PHPeste\Ticketing\Data\Allocation;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Enums\TicketStatus;
use PHPeste\Ticketing\Events\OrderRefunded;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\OrderItem;
use PHPeste\Ticketing\Models\Ticket;

class ApplyRefundToOrder
{
    public function __construct(
        private readonly CurrentEdition $currentEdition,
        private readonly ReleaseTickets $releaseTickets,
        private readonly EditionMailer $mailer,
    ) {}

    public function handle(RefundAccepted $event): void
    {
        $this->currentEdition->run($event->editionId, function () use ($event): void {
            $order = Order::query()->find($event->reference);

            if (!$order instanceof Order) {
                return;
            }

            $itemIds = array_values(array_filter((array) ($event->metadata['item_ids'] ?? []), is_string(...)));

            $cancelled = DB::transaction(function () use ($order, $event, $itemIds): int {
                Order::query()->whereKey($order->id)->increment('refunded_cents', $event->amountCents, ['updated_at' => now()]);

                if ($itemIds === [] || !in_array($order->status, [OrderStatus::Paid, OrderStatus::Refunded], true)) {
                    return 0;
                }

                $items = OrderItem::query()->where('order_id', $order->id)->whereIn('id', $itemIds)->whereNull('cancelled_at')->get();
                $count = 0;

                foreach ($items->groupBy('ticket_batch_id') as $batchId => $group) {
                    $changed = OrderItem::query()->whereIn('id', $group->pluck('id'))->whereNull('cancelled_at')->update(['cancelled_at' => now(), 'refund_id' => $event->refundId, 'updated_at' => now()]);
                    $first = $group->first();

                    if ($changed > 0 && $first instanceof OrderItem) {
                        $this->releaseTickets->handle(new Allocation($order->edition_id, $first->ticket_type_id, (string) $batchId, $first->batch_name, $changed, $first->unit_price_cents));
                        $count += $changed;
                    }
                }

                Ticket::query()->where('order_id', $order->id)
                    ->whereIn('order_item_id', OrderItem::query()->where('refund_id', $event->refundId)->select('id'))
                    ->where('status', TicketStatus::Active->value)
                    ->update(['status' => TicketStatus::Cancelled->value, 'cancelled_at' => now(), 'updated_at' => now()]);

                return $count;
            });

            event(new OrderRefunded($order->edition_id, $order->id, $event->refundId, $event->amountCents, $event->reason->value, $event->note, $cancelled, $event->actorId));

            $this->mailer->send($order->edition_id, EmailTemplate::OrderRefunded, $order->buyer_email, $order->buyer_name, [
                'nome' => $order->buyer_name,
                'pedido' => sprintf('#%d', $order->number),
                'valor' => 'R$ '.number_format($event->amountCents / 100, 2, ',', '.'),
                'ingressos' => $cancelled === 0 ? 'Seus ingressos continuam valendo.' : sprintf('%d %s cancelado%s.', $cancelled, $cancelled === 1 ? 'ingresso foi' : 'ingressos foram', $cancelled === 1 ? '' : 's'),
            ]);
        });
    }
}
