<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Branding\Contracts\EditionSites;
use PHPeste\Notifications\Contracts\EditionMailer;
use PHPeste\Notifications\Enums\EmailTemplate;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Events\PaymentReminderSent;
use PHPeste\Ticketing\Models\Order;

/**
 * Reenvia para quem comprou o link de pagamento de um pedido que ainda
 * está esperando. A cobrança é a mesma: o link leva para o checkout do pedido.
 */
class SendPaymentReminder
{
    public function __construct(
        private readonly EditionMailer $mailer,
        private readonly EditionSites $sites,
    ) {}

    public function handle(Order $order, ?string $actorId): string
    {
        if ($order->status !== OrderStatus::AwaitingPayment) {
            throw ValidationException::withMessages(['order' => 'Só dá para reenviar a cobrança de um pedido aguardando pagamento.']);
        }

        if ($order->expires_at === null || $order->expires_at->lte(now())) {
            throw ValidationException::withMessages(['order' => 'A reserva deste pedido expirou; a cobrança não vale mais.']);
        }

        $deliveryId = $this->mailer->send($order->edition_id, EmailTemplate::PixReminder, $order->buyer_email, $order->buyer_name, [
            'nome' => $order->buyer_name,
            'pedido' => sprintf('#%d', $order->number),
        ], mb_rtrim($this->sites->url($order->edition_id), '/')."/checkout/pedido/{$order->id}");

        event(new PaymentReminderSent($order->edition_id, $order->id, $deliveryId, $actorId));

        return $deliveryId;
    }
}
