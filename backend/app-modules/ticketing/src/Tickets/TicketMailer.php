<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Tickets;

use PHPeste\Branding\Contracts\EditionSites;
use PHPeste\Notifications\Contracts\EditionMailer;
use PHPeste\Notifications\Enums\EmailTemplate;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\Ticket;

class TicketMailer
{
    public function __construct(
        private readonly EditionMailer $mailer,
        private readonly EditionSites $sites,
    ) {}

    public function issued(Order $order, Ticket $ticket): void
    {
        $this->send($ticket, EmailTemplate::OrderConfirmed, ['pedido' => sprintf('#%d', $order->number)]);
    }

    public function transferred(Ticket $ticket, string $fromName): void
    {
        $this->send($ticket, EmailTemplate::TicketTransferred, ['remetente' => $fromName, 'ingresso' => $ticket->code]);
    }

    public function dataReminder(Ticket $ticket): void
    {
        $this->mailer->send($ticket->edition_id, EmailTemplate::AttendeeDataReminder, $ticket->holder_email, $ticket->holder_name, ['nome' => $ticket->holder_name], $this->ticketsUrl($ticket->edition_id));
    }

    /**
     * A cobrança de dados como a pessoa vai receber, sem enviar.
     *
     * @return array{subject: string, html: string, text: string}
     */
    public function previewDataReminder(string $editionId, string $email, string $name): array
    {
        return $this->mailer->preview($editionId, EmailTemplate::AttendeeDataReminder, $email, ['nome' => $name], $this->ticketsUrl($editionId));
    }

    private function ticketsUrl(string $editionId): string
    {
        return mb_rtrim($this->sites->url($editionId), '/').'/ingressos';
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function send(Ticket $ticket, EmailTemplate $template, array $variables): void
    {
        $this->mailer->send($ticket->edition_id, $template, $ticket->holder_email, $ticket->holder_name, [
            'nome' => $ticket->holder_name,
            ...$variables,
        ], $this->ticketsUrl($ticket->edition_id));

        Ticket::query()->forEdition($ticket->edition_id)->whereKey($ticket->id)->update(['last_sent_at' => now()]);
    }
}
