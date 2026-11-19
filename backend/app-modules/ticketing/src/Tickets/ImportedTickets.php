<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Tickets;

use Illuminate\Database\Eloquent\Builder;
use PHPeste\Ticketing\Enums\TicketStatus;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\Ticket;
use PHPeste\Ticketing\Models\TicketImport;

final class ImportedTickets
{
    /**
     * Ingressos válidos da importação que ainda não foram por e-mail.
     *
     * @return Builder<Ticket>
     */
    public function unsent(TicketImport $import): Builder
    {
        return Ticket::query()->forEdition($import->edition_id)
            ->where('status', TicketStatus::Active->value)
            ->whereNull('last_sent_at')
            ->whereIn('order_id', Order::query()->forEdition($import->edition_id)->where('ticket_import_id', $import->id)->select('id'));
    }
}
