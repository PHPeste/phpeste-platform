<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPeste\Community\Contracts\EditionDirectory;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Ticketing\Enums\TicketStatus;
use PHPeste\Ticketing\Events\TicketTransferred;
use PHPeste\Ticketing\Models\Ticket;
use PHPeste\Ticketing\Models\TicketTransfer;
use PHPeste\Ticketing\Tickets\TicketMailer;

class TransferTicket
{
    public const string BY_HOLDER = 'holder';

    public const string BY_ORGANIZER = 'organizer';

    public function __construct(
        private readonly EditionDirectory $editions,
        private readonly TicketMailer $mail,
    ) {}

    public function handle(Ticket $ticket, string $name, string $email, string $channel, ?string $actorId): Ticket
    {
        $email = Str::lower(mb_trim($email));
        $name = mb_trim($name);

        if (!$ticket->isActive()) {
            throw ValidationException::withMessages(['ticket' => 'Ingresso cancelado não pode ser transferido.']);
        }

        if (($this->editions->summary($ticket->edition_id)['status'] ?? null) === EditionStatus::Closed->value) {
            throw ValidationException::withMessages(['ticket' => 'A edição já terminou; o ingresso não pode mais ser transferido.']);
        }

        if ($email === $ticket->holder_email) {
            throw ValidationException::withMessages(['email' => 'O ingresso já está com este e-mail.']);
        }

        $from = ['name' => $ticket->holder_name, 'email' => $ticket->holder_email];

        $transfer = DB::transaction(function () use ($ticket, $name, $email, $channel, $actorId, $from): TicketTransfer {
            $moved = Ticket::query()->forEdition($ticket->edition_id)
                ->whereKey($ticket->id)
                ->where('qr_version', $ticket->qr_version)
                ->where('status', TicketStatus::Active->value)
                ->update([
                    'holder_name' => $name,
                    'holder_email' => $email,
                    'holder_user_id' => null,
                    'badge_name' => null,
                    'company' => null,
                    'tshirt_size' => null,
                    'accessibility' => null,
                    'qr_version' => $ticket->qr_version + 1,
                    'updated_at' => now(),
                ]);

            if ($moved !== 1) {
                throw ValidationException::withMessages(['ticket' => 'O ingresso mudou enquanto isso. Atualize e tente de novo.']);
            }

            return TicketTransfer::create([
                'edition_id' => $ticket->edition_id,
                'ticket_id' => $ticket->id,
                'from_name' => $from['name'],
                'from_email' => $from['email'],
                'to_name' => $name,
                'to_email' => $email,
                'transferred_by' => $actorId,
                'channel' => $channel,
            ]);
        });

        event(new TicketTransferred($ticket->edition_id, $ticket->id, $transfer->id, $channel, $actorId));

        $ticket->refresh();
        $this->mail->transferred($ticket, $from['name']);

        return $ticket;
    }
}
