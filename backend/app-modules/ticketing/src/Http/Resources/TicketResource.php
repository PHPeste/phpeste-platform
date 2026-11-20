<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Ticketing\Models\Ticket;
use PHPeste\Ticketing\Models\TicketTransfer;
use PHPeste\Ticketing\Tickets\AttendeeData;
use PHPeste\Ticketing\Tickets\TicketPasses;

class TicketResource extends ApiResource
{
    public bool $withQr = false;

    public bool $withAccessibility = false;

    public bool $withHistory = false;

    /**
     * @var list<array<string, mixed>>|null
     */
    public ?array $edits = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ticket = $this->resource;

        if (!$ticket instanceof Ticket) {
            return [];
        }

        return [
            'id' => $ticket->id,
            'code' => $ticket->code,
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'order_id' => $ticket->order_id,
            'order_number' => $ticket->relationLoaded('order') ? $ticket->order->number : null,
            'ticket_type_id' => $ticket->ticket_type_id,
            'ticket_type_name' => $ticket->relationLoaded('item') ? $ticket->item->ticket_type_name : null,
            'batch_name' => $ticket->relationLoaded('item') ? $ticket->item->batch_name : null,
            'holder' => [
                'name' => $ticket->holder_name,
                'email' => $ticket->holder_email,
                'badge_name' => $ticket->badge_name,
                'company' => $ticket->company,
                'tshirt_size' => $ticket->tshirt_size?->value,
                ...($this->withAccessibility ? ['accessibility' => $ticket->accessibility] : []),
            ],
            'missing' => resolve(AttendeeData::class)->missingFields($ticket),
            'transferred_from' => is_string($ticket->getAttribute('transferred_from')) ? $ticket->getAttribute('transferred_from') : null,
            'issued_at' => $this->isoDate($ticket->issued_at),
            'cancelled_at' => $this->isoDate($ticket->cancelled_at),
            'last_sent_at' => $this->isoDate($ticket->last_sent_at),
            ...($this->withQr && $ticket->isActive() ? ['qr' => resolve(TicketPasses::class)->token($ticket)] : []),
            ...($this->withHistory ? ['transfers' => $ticket->transfers()->oldest()->get()->map(fn (TicketTransfer $transfer): array => [
                'from' => ['name' => $transfer->from_name, 'email' => $transfer->from_email],
                'to' => ['name' => $transfer->to_name, 'email' => $transfer->to_email],
                'channel' => $transfer->channel,
                'transferred_by' => $transfer->transferred_by,
                'at' => $this->isoDate($transfer->created_at),
            ])->all()] : []),
            ...($this->edits === null ? [] : ['edits' => $this->edits]),
        ];
    }
}
