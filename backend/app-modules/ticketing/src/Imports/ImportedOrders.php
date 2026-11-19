<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Imports;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Str;
use PHPeste\Ticketing\Actions\IssueTickets;
use PHPeste\Ticketing\Enums\OrderSource;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\OrderItem;
use PHPeste\Ticketing\Models\Ticket;
use PHPeste\Ticketing\Models\TicketImport;
use PHPeste\Ticketing\Models\TicketingSetting;
use PHPeste\Ticketing\Models\TicketType;

/**
 * Grava uma linha aceita: o pedido externo (pago, sem cobrança e sem taxa),
 * o item e o ingresso, sem mandar e-mail. Linhas com o mesmo número de
 * pedido da mesma plataforma entram no mesmo pedido, inclusive vindas de
 * outra importação. Roda dentro da transação do lote de linhas.
 */
final readonly class ImportedOrders
{
    public function __construct(private IssueTickets $issue) {}

    /**
     * @return string|null motivo da rejeição, ou `null` se entrou
     */
    public function write(TicketImport $import, ImportRow $row, TicketType $type, int $line): ?string
    {
        if (!$this->takeSeat($import->edition_id)) {
            return 'A capacidade da edição esgotou.';
        }

        $order = $this->order($import, $row, $line);

        $item = OrderItem::create([
            'edition_id' => $import->edition_id,
            'order_id' => $order->id,
            'ticket_type_id' => $type->id,
            'ticket_batch_id' => null,
            'ticket_type_name' => $type->name,
            'batch_name' => mb_substr('Importado de '.$import->platform, 0, 80),
            'unit_price_cents' => 0,
            'discount_cents' => 0,
            'fee_cents' => 0,
            'total_cents' => 0,
            'attendee_name' => $row->name,
            'attendee_email' => $row->email,
            'external_code' => $row->ticketCode,
        ]);

        $this->issue->handle($order, notify: false);

        if ($row->badgeName !== null || $row->company !== null) {
            Ticket::query()->forEdition($import->edition_id)->where('order_item_id', $item->id)
                ->update(['badge_name' => $row->badgeName, 'company' => $row->company]);
        }

        return null;
    }

    /**
     * Ingresso importado também ocupa a capacidade da edição, para a venda
     * por aqui não passar do total. Lote não: a venda foi em outro lugar.
     */
    private function takeSeat(string $editionId): bool
    {
        TicketingSetting::query()->forEdition($editionId)->firstOrCreate(['edition_id' => $editionId]);

        return TicketingSetting::query()->forEdition($editionId)
            ->where(fn (Builder $query) => $query->whereNull('capacity')->orWhereRaw('allocated + 1 <= capacity'))
            ->increment('allocated') > 0;
    }

    private function order(TicketImport $import, ImportRow $row, int $line): Order
    {
        if ($row->orderReference !== null) {
            $existing = Order::query()->forEdition($import->edition_id)
                ->where('external_platform', $import->platform)
                ->where('external_reference', $row->orderReference)
                ->first();

            if ($existing instanceof Order) {
                return $existing;
            }
        }

        $settings = TicketingSetting::query()->forEdition($import->edition_id)->firstOrFail();
        TicketingSetting::query()->forEdition($import->edition_id)->whereKey($settings->id)->increment('order_sequence');
        $number = (int) TicketingSetting::query()->forEdition($import->edition_id)->whereKey($settings->id)->value('order_sequence');
        $key = sprintf('import:%s:%d', $import->id, $line);

        return Order::create([
            'edition_id' => $import->edition_id,
            'number' => $number,
            'status' => OrderStatus::Paid,
            'source' => OrderSource::Import,
            'external_platform' => $import->platform,
            'external_reference' => $row->orderReference,
            'ticket_import_id' => $import->id,
            'buyer_name' => $row->buyerName,
            'buyer_email' => $row->buyerEmail,
            'subtotal_cents' => 0,
            'discount_cents' => 0,
            'fee_cents' => 0,
            'total_cents' => 0,
            'access_token' => Str::random(48),
            'idempotency_key' => $key,
            'request_hash' => hash('sha256', $key),
            'paid_at' => now(),
        ]);
    }
}
