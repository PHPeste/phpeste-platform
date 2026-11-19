<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Contracts;

/**
 * Receita de patrocínio para o Finance, em centavos: o que já entrou
 * (pagamentos registrados) e o que falta receber dos acordos fechados.
 */
interface SponsorRevenue
{
    /**
     * @return array{received: int, outstanding: int, deals: int}
     */
    public function summary(string $editionId): array;
}
