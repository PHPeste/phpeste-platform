<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Orders;

use PHPeste\Community\Contracts\EditionTransitionGuard;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Payments\Contracts\PaymentAccounts;
use PHPeste\Ticketing\Models\TicketBatch;

class SalesReadinessGuard implements EditionTransitionGuard
{
    public const string MESSAGE = 'Vendas pausadas até o teste do gateway: valide as chaves e receba um evento de teste do webhook em Pagamentos.';

    public function __construct(private readonly PaymentAccounts $accounts) {}

    public function blockers(string $editionId, EditionStatus $next): array
    {
        if ($next !== EditionStatus::SalesOpen) {
            return [];
        }

        $paidBatches = TicketBatch::query()->forEdition($editionId)->where('price_cents', '>', 0)->exists();

        return $paidBatches && !$this->accounts->ready($editionId) ? [self::MESSAGE] : [];
    }
}
