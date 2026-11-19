<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\TicketImport;
use PHPeste\Ticketing\Tickets\ImportedTickets;
use PHPeste\Ticketing\Tickets\TicketMailer;

/**
 * Enfileira o e-mail de cada ingresso importado ainda não enviado. Cada
 * envio marca `last_sent_at`, então repetir o job não manda duas vezes.
 */
class SendImportedTicketEmails implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public readonly string $editionId,
        public readonly string $importId,
    ) {
        $this->afterCommit();
    }

    public function handle(CurrentEdition $currentEdition, ImportedTickets $tickets, TicketMailer $mailer): void
    {
        $currentEdition->run($this->editionId, function () use ($tickets, $mailer): void {
            $import = TicketImport::query()->find($this->importId);

            if (!$import instanceof TicketImport || $import->emails_requested_at === null) {
                return;
            }

            $tickets->unsent($import)->chunkById(200, function ($chunk) use ($mailer): void {
                $orders = Order::query()->whereIn('id', $chunk->pluck('order_id')->unique())->get()->keyBy('id');

                foreach ($chunk as $ticket) {
                    $order = $orders->get($ticket->order_id);

                    if ($order instanceof Order) {
                        $mailer->issued($order, $ticket);
                    }
                }
            });
        });
    }
}
