<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Ticketing\Actions\RemindMissingAttendeeData;
use PHPeste\Ticketing\Actions\TransferTicket;
use PHPeste\Ticketing\Actions\UpdateTicketAttendee;
use PHPeste\Ticketing\Enums\TicketStatus;
use PHPeste\Ticketing\Enums\TshirtSize;
use PHPeste\Ticketing\Http\Requests\RemindAttendeeDataRequest;
use PHPeste\Ticketing\Http\Requests\TransferTicketRequest;
use PHPeste\Ticketing\Http\Requests\UpdateTicketAttendeeRequest;
use PHPeste\Ticketing\Http\Resources\TicketResource;
use PHPeste\Ticketing\Models\Ticket;
use PHPeste\Ticketing\Tickets\AttendeeData;
use PHPeste\Ticketing\Tickets\AttendeeHistory;
use PHPeste\Ticketing\Tickets\BadgeExport;
use PHPeste\Ticketing\Tickets\TicketMailer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController
{
    public function __construct(private readonly AttendeeData $data) {}

    /**
     * Uma linha por ingresso. Filtros: `status`, `tshirt_size`,
     * `ticket_type_id`, `q` (nome, crachá, e-mail ou código), `missing`
     * (sem crachá ou camiseta), `transferred` e `accessibility` (só a
     * organização). `meta.summary` traz os números da edição inteira.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('ticketing.view');

        $private = Gate::allows(EditionPermission::ManageEdition->value);
        $status = TicketStatus::tryFrom((string) $request->query('status'));
        $size = TshirtSize::tryFrom((string) $request->query('tshirt_size'));
        $type = $request->query('ticket_type_id');
        $search = mb_trim((string) $request->query('q'));

        $tickets = $this->data->withPreviousHolder(Ticket::query()->select('tickets.*')->with(['item', 'order']))
            ->when($request->boolean('missing'), fn (Builder $query): Builder => $this->data->missing($query))
            ->when($request->boolean('transferred'), fn (Builder $query): Builder => $this->data->transferred($query))
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

        return $collection->additional(['meta' => ['summary' => $this->data->summary($private)]]);
    }

    public function show(Request $request, AttendeeHistory $history): TicketResource
    {
        Gate::authorize('ticketing.view');

        $ticket = $this->ticket($request);
        $resource = $this->present($ticket, history: true);
        $resource->edits = $history->for($ticket);

        return $resource;
    }

    public function update(UpdateTicketAttendeeRequest $request, UpdateTicketAttendee $update, AttendeeHistory $history): TicketResource
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        /** @var array<string, mixed> $data */
        $data = $request->validated();
        $actor = $request->user()?->getAuthIdentifier();

        $ticket = $update->handle($this->ticket($request), $data, is_string($actor) ? $actor : null)->load(['item', 'order']);
        $resource = $this->present($ticket, history: true);
        $resource->edits = $history->for($ticket);

        return $resource;
    }

    /**
     * Quantas pessoas vão receber a cobrança de dados e o e-mail como sai.
     */
    public function reminderPreview(Request $request, RemindMissingAttendeeData $remind, TicketMailer $mail, CurrentEdition $currentEdition): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $first = $this->data->missing(Ticket::query())->orderBy('holder_name')->first();

        return response()->json(['data' => [
            'recipients' => $remind->recipients(),
            'email' => $first === null ? null : $mail->previewDataReminder((string) $currentEdition->id(), $first->holder_email, $first->holder_name),
        ]]);
    }

    /**
     * Manda a cobrança. `recipients` precisa bater com o número da prévia.
     */
    public function remind(RemindAttendeeDataRequest $request, RemindMissingAttendeeData $remind, CurrentEdition $currentEdition): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $actor = $request->user()?->getAuthIdentifier();
        $sent = $remind->handle((string) $currentEdition->id(), $request->boolean('confirm'), $request->integer('recipients'), is_string($actor) ? $actor : null);

        return response()->json(['data' => ['recipients' => $sent]], 202);
    }

    /**
     * CSV dos ingressos válidos para imprimir os crachás.
     */
    public function badges(BadgeExport $export, CurrentEdition $currentEdition): StreamedResponse
    {
        Gate::authorize('ticketing.view');

        $editionId = (string) $currentEdition->id();

        return response()->streamDownload(fn () => $currentEdition->run($editionId, function () use ($export): void {
            $out = fopen('php://output', 'w');

            if ($out !== false) {
                $export->write($out);
                fclose($out);
            }
        }), sprintf('crachas-%s.csv', now()->format('Y-m-d')), ['Content-Type' => 'text/csv; charset=UTF-8']);
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
