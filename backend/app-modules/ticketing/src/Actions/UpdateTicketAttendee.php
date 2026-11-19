<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Models\Ticket;

class UpdateTicketAttendee
{
    public const array FIELDS = ['holder_name', 'badge_name', 'company', 'tshirt_size', 'accessibility'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Ticket $ticket, array $data): Ticket
    {
        if (!$ticket->isActive()) {
            throw ValidationException::withMessages(['ticket' => 'Ingresso cancelado não pode ser alterado.']);
        }

        $ticket->update(array_intersect_key($data, array_flip(self::FIELDS)));

        return $ticket->refresh();
    }
}
