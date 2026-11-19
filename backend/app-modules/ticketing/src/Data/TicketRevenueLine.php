<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Data;

/**
 * Receita de ingressos de uma conta de recebimento, em centavos.
 * `net` = `gross` − `refunded` − `fees`. Taxa desconhecida (o gateway ainda
 * não informou) é estimada pelo percentual da edição e contada em
 * `estimatedFees`.
 */
final readonly class TicketRevenueLine
{
    public function __construct(
        public ?string $paymentAccountId,
        public int $orders,
        public int $grossCents,
        public int $refundedCents,
        public int $feeCents,
        public int $estimatedFees,
    ) {}

    public function netCents(): int
    {
        return $this->grossCents - $this->refundedCents - $this->feeCents;
    }
}
