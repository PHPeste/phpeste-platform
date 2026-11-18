<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Branding\Contracts\EditionSites;
use PHPeste\Community\Contracts\EditionDirectory;
use PHPeste\Payments\Contracts\PaymentProcessor;
use PHPeste\Payments\Data\Payer;
use PHPeste\Payments\Data\PaymentAttempt;
use PHPeste\Payments\Data\StartPayment;
use PHPeste\Payments\Enums\PaymentMethod;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Models\Order;

class StartOrderPayment
{
    public function __construct(
        private readonly PaymentProcessor $payments,
        private readonly TransitionOrder $transition,
        private readonly EditionDirectory $editions,
        private readonly EditionSites $sites,
    ) {}

    public function handle(Order $order, PaymentMethod $method, int $installments, ?string $cardToken, ?string $remoteIp): PaymentAttempt
    {
        if (!in_array($order->status, [OrderStatus::Created, OrderStatus::AwaitingPayment], true)) {
            throw ValidationException::withMessages(['payment' => sprintf('Este pedido está %s e não aceita pagamento.', mb_strtolower($order->status->label()))]);
        }

        if ($order->expires_at === null || $order->expires_at->lte(now())) {
            throw ValidationException::withMessages(['payment' => 'A reserva deste pedido expirou. Escolha os ingressos de novo.']);
        }

        if ($order->total_cents === 0 || $order->payment_account_id === null || $order->buyer_document === null) {
            throw ValidationException::withMessages(['payment' => 'Este pedido não precisa de pagamento.']);
        }

        $edition = $this->editions->summary($order->edition_id);

        $attempt = $this->payments->start(new StartPayment(
            editionId: $order->edition_id,
            accountId: $order->payment_account_id,
            reference: $order->id,
            amountCents: $order->total_cents,
            description: sprintf('%s · pedido #%d', $edition['name'] ?? 'Ingressos', $order->number),
            payer: new Payer($order->buyer_name, $order->buyer_email, $order->buyer_document),
            method: $method,
            installments: $installments,
            expiresAt: $order->expires_at,
            returnUrl: mb_rtrim($this->sites->url($order->edition_id), '/')."/checkout/pedido/{$order->id}",
            cardToken: $cardToken,
            remoteIp: $remoteIp,
        ));

        if ($order->refresh()->status === OrderStatus::Created) {
            $this->transition->handle($order, OrderStatus::AwaitingPayment, null, $method->value);
        }

        return $attempt;
    }
}
