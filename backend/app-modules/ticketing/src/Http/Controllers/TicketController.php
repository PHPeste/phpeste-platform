<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Ticketing\Actions\TransferTicket;
use PHPeste\Ticketing\Actions\UpdateTicketAttendee;
use PHPeste\Ticketing\Enums\TicketStatus;
use PHPeste\Ticketing\Enums\TshirtSize;
use PHPeste\Ticketing\Http\Requests\TransferTicketRequest;
use PHPeste\Ticketing\Http\Requests\UpdateTicketAttendeeRequest;
use PHPeste\Ticketing\Http\Resources\TicketResource;
use PHPeste\Ticketing\Models\Ticket;
use PHPeste\Ticketing\Tickets\TicketMailer;

class TicketController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('ticketing.view');

        $private = Gate::allows(EditionPermission::ManageEdition->value);
        $status = TicketStatus::tryFrom((string) $request->query('status'));
        $size = TshirtSize::tryFrom((string) $request->query('tshirt_size'));
        $type = $request->query('ticket_type_id');
        $search = mb_trim((string) $request->query('q'));

        $tickets = Ticket::query()->with(['item', 'order'])
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status?->value))
            ->when($size !== null, fn (Builder $query) => $query->where('tshirt_size', $size?->value))
            ->when(is_string($type) && Str::isUuid($type), fn (Builder $query) => $query->where('ticket_type_id', $type))
            ->when($private && $request->boolean('accessibility'), fn (Builder $query) => $query->whereNotNull('accessibility'))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('holder_name', 'like', "%{$search}%")
                ->orWhere('badge_name', 'like', "%{$search}%")
                ->orWhere('holder_email', 'like', '%'.Str::lower($search).'%')
                ->orWhere('code', Str::upper($search))))
            ->orderBy('holder_name')
            ->paginate(50);

        $collection = TicketResource::collection($tickets);

        foreach ($collection->collection ?? [] as $resource) {
            if ($resource instanceof TicketResource) {
                $resource->withAccessibility = $private;
            }
        }

        return $collection;
    }

    public function show(Request $request): TicketResource
    {
        Gate::authorize('ticketing.view');

        return $this->present($this->ticket($request), history: true);
    }

    public function update(UpdateTicketAttendeeRequest $request, UpdateTicketAttendee $update): TicketResource
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        /** @var array<string, mixed> $data */
        $data = $request->validated();

        return $this->present($update->handle($this->ticket($request), $data)->load(['item', 'order']));
    }

    public function transfer(TransferTicketRequest $request, TransferTicket $transfer): TicketResource
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $actor = $request->user()?->getAuthIdentifier();
        $ticket = $transfer->handle($this->ticket($request), (string) $request->string('name'), (string) $request->string('email'), TransferTicket::BY_ORGANIZER, is_string($actor) ? $actor : null);

        return $this->present($ticket->load(['item', 'order']), history: true);
    }

    public function resend(Request $request, TicketMailer $mail): TicketResource
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $ticket = $this->ticket($request);
        abort_unless($ticket->isActive(), 422, 'Ingresso cancelado.');
        $mail->issued($ticket->order, $ticket);

        return $this->present($ticket->refresh()->load(['item', 'order']));
    }

    private function ticket(Request $request): Ticket
    {
        $id = $request->route('ticket');
        abort_unless(is_string($id), 404);

        return Ticket::query()->with(['item', 'order'])->whereKey($id)->firstOrFail();
    }

    private function present(Ticket $ticket, bool $history = false): TicketResource
    {
        $resource = new TicketResource($ticket);
        $resource->withAccessibility = Gate::allows(EditionPermission::ManageEdition->value);
        $resource->withHistory = $history;

        return $resource;
    }
}
