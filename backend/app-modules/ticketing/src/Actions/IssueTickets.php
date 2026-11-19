<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use PHPeste\Ticketing\Enums\TicketStatus;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\OrderItem;
use PHPeste\Ticketing\Models\Ticket;
use PHPeste\Ticketing\Tickets\TicketMailer;

class IssueTickets
{
    public const string ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function __construct(private readonly TicketMailer $mail) {}

    public static function code(): string
    {
        $code = '';

        for ($i = 0; $i < 8; $i++) {
            $code .= self::ALPHABET[random_int(0, mb_strlen(self::ALPHABET) - 1)];
        }

        return mb_substr($code, 0, 4).'-'.mb_substr($code, 4);
    }

    /**
     * Com `$notify = false` (importação de outra plataforma) o ingresso é
     * emitido sem e-mail; o envio fica para quando a organização confirmar.
     *
     * @return list<Ticket>
     */
    public function handle(Order $order, bool $notify = true): array
    {
        $items = OrderItem::query()->forEdition($order->edition_id)
            ->where('order_id', $order->id)
            ->whereNull('cancelled_at')
            ->whereDoesntHave('ticket')
            ->orderBy('id')
            ->get();

        $issued = [];

        foreach ($items as $item) {
            $ticket = $this->issue($order, $item);

            if ($ticket instanceof Ticket) {
                $issued[] = $ticket;

                if ($notify) {
                    $this->mail->issued($order, $ticket);
                }
            }
        }

        return $issued;
    }

    private function issue(Order $order, OrderItem $item): ?Ticket
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return Ticket::create([
                    'edition_id' => $order->edition_id,
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'ticket_type_id' => $item->ticket_type_id,
                    'code' => self::code(),
                    'status' => TicketStatus::Active,
                    'holder_name' => $item->attendee_name,
                    'holder_email' => Str::lower($item->attendee_email),
                    'issued_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                if (Ticket::query()->forEdition($order->edition_id)->where('order_item_id', $item->id)->exists()) {
                    return null;
                }
            }
        }

        return null;
    }
}
