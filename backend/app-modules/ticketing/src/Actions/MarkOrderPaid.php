<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Payments\Contracts\PaymentProcessor;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Models\Order;

/**
 * Confirma à mão um pagamento que entrou fora do gateway (transferência,
 * dinheiro na secretaria). Emite os ingressos como um pagamento normal, cancela
 * a cobrança que estava aberta e deixa a justificativa na auditoria.
 */
class MarkOrderPaid
{
    public const REASON_PREFIX = 'manual: ';

    public function __construct(
        private readonly TransitionOrder $transition,
        private readonly PaymentProcessor $payments,
    ) {}

    public function handle(Order $order, string $justification, ?string $actorId): Order
    {
        if (!in_array($order->status, [OrderStatus::Created, OrderStatus::AwaitingPayment], true)) {
            throw ValidationException::withMessages(['order' => sprintf('Só pedido esperando pagamento pode ser marcado como pago; este está %s.', mb_strtolower($order->status->label()))]);
        }

        if ($order->total_cents === 0) {
            throw ValidationException::withMessages(['order' => 'Pedido sem valor a pagar não precisa ser marcado como pago.']);
        }

        $paid = $this->transition->handle($order, OrderStatus::Paid, $actorId, self::REASON_PREFIX.$justification);
        $this->payments->cancelPending($order->edition_id, $order->id);

        return $paid;
    }
}
