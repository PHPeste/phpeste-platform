<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Contracts;

use PHPeste\Ticketing\Data\TicketRevenueLine;

/**
 * Receita de ingressos para o Finance: pedidos pagos (inclusive os
 * reembolsados depois), com cobrança, agrupados pela conta que recebeu.
 * Pedido gratuito, cortesia e pedido importado de outra plataforma não
 * entram: não passaram por aqui.
 */
interface TicketRevenue
{
    /**
     * @return list<TicketRevenueLine>
     */
    public function byAccount(string $editionId): array;
}
