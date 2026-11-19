<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Ticketing\Actions\TransferTicket;
use PHPeste\Ticketing\Actions\UpdateTicketAttendee;
use PHPeste\Ticketing\Http\Requests\TransferTicketRequest;
use PHPeste\Ticketing\Http\Requests\UpdateTicketAttendeeRequest;
use PHPeste\Ticketing\Http\Resources\TicketResource;
use PHPeste\Ticketing\Models\Ticket;
use PHPeste\Ticketing\Tickets\TicketMailer;
use PHPeste\Ticketing\Tickets\TicketOwnership;

class AttendeeTicketController
{
    public function __construct(
        private readonly CurrentEdition $currentEdition,
        private readonly TicketOwnership $ownership,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $tickets = $this->ownership->query((string) $this->currentEdition->id(), $user)->with(['item', 'order'])->oldest('issued_at')->get();

        foreach ($tickets as $ticket) {
            $this->ownership->claim($ticket, $user);
        }

        return response()->json([
            'data' => $tickets->map(fn (Ticket $ticket): array => $this->present($ticket)->resolve($request))->all(),
            'meta' => ['email_verified' => $this->ownership->verifiedEmail($user) !== null],
        ]);
    }

    public function show(Request $request): TicketResource
    {
        return $this->present($this->ticket($request), history: true);
    }

    public function update(UpdateTicketAttendeeRequest $request, UpdateTicketAttendee $update): TicketResource
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        return $this->present($update->handle($this->ticket($request), $data)->load(['item', 'order']));
    }

    public function transfer(TransferTicketRequest $request, TransferTicket $transfer): TicketResource
    {
        $ticket = $transfer->handle($this->ticket($request), (string) $request->string('name'), (string) $request->string('email'), TransferTicket::BY_HOLDER, (string) $this->user($request)->getAuthIdentifier());

        return new TicketResource($ticket->load(['item', 'order']));
    }

    public function resend(Request $request, TicketMailer $mail): TicketResource
    {
        $ticket = $this->ticket($request);
        abort_unless($ticket->isActive(), 422, 'Ingresso cancelado.');
        $mail->issued($ticket->order, $ticket);

        return $this->present($ticket->refresh()->load(['item', 'order']));
    }

    private function ticket(Request $request): Ticket
    {
        $id = $request->route('ticket');
        abort_unless(is_string($id), 404);

        $user = $this->user($request);
        $ticket = $this->ownership->query((string) $this->currentEdition->id(), $user)->with(['item', 'order'])->whereKey($id)->firstOrFail();
        $this->ownership->claim($ticket, $user);

        return $ticket;
    }

    private function present(Ticket $ticket, bool $history = false): TicketResource
    {
        $resource = new TicketResource($ticket);
        $resource->withQr = true;
        $resource->withAccessibility = true;
        $resource->withHistory = $history;

        return $resource;
    }

    private function user(Request $request): Authenticatable
    {
        $user = $request->user();
        abort_unless($user instanceof Authenticatable, 401);

        return $user;
    }
}
