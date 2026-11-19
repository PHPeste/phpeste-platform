<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Tickets;

use PHPeste\Ticketing\Contracts\TicketVerifier;
use PHPeste\Ticketing\Data\TicketCheck;
use PHPeste\Ticketing\Models\OrderItem;
use PHPeste\Ticketing\Models\Ticket;

class TicketPasses implements TicketVerifier
{
    public function check(string $editionId, string $qrToken): TicketCheck
    {
        $read = QrToken::fromConfig()->read($qrToken);

        if ($read === null) {
            return new TicketCheck(TicketCheck::UNKNOWN);
        }

        $ticket = Ticket::query()->forEdition($editionId)->find($read['ticket_id']);

        if (!$ticket instanceof Ticket) {
            return new TicketCheck(TicketCheck::UNKNOWN);
        }

        $result = match (true) {
            $read['version'] !== $ticket->qr_version => TicketCheck::REPLACED,
            !$ticket->isActive() => TicketCheck::CANCELLED,
            default => TicketCheck::VALID,
        };

        return $result === TicketCheck::VALID
            ? new TicketCheck($result, $ticket->id, $ticket->code, $ticket->holder_name, $ticket->badge_name, (string) OrderItem::query()->forEdition($editionId)->whereKey($ticket->order_item_id)->value('ticket_type_name'))
            : new TicketCheck($result, $ticket->id, $ticket->code);
    }

    public function token(Ticket $ticket): string
    {
        return QrToken::fromConfig()->make($ticket->id, $ticket->qr_version);
    }
}
