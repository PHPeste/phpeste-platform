<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Overview;

use Illuminate\Support\Collection;
use PHPeste\Suppliers\Enums\SupplierStatus;
use PHPeste\Suppliers\Models\EditionSupplier;

/**
 * Totais calculados sobre a mesma lista que a tela mostra, para baterem:
 * orçado é a soma do menor orçamento válido de cada fornecedor não recusado;
 * contratado é a soma do valor dos contratados.
 */
final class SupplierTotals
{
    /**
     * @param  Collection<int, EditionSupplier>  $links  com `quotes` carregado
     * @return array{count: int, quoted: int, contracted: int, by_status: array<string, int>}
     */
    public function of(Collection $links): array
    {
        $byStatus = [];

        foreach (SupplierStatus::cases() as $status) {
            $byStatus[$status->value] = 0;
        }

        $quoted = 0;
        $contracted = 0;

        foreach ($links as $link) {
            $byStatus[$link->status->value]++;

            if ($link->status !== SupplierStatus::Declined) {
                $quoted += $link->bestQuoteCents() ?? 0;
            }

            if ($link->status === SupplierStatus::Hired) {
                $contracted += $link->contracted_cents ?? 0;
            }
        }

        return ['count' => $links->count(), 'quoted' => $quoted, 'contracted' => $contracted, 'by_status' => $byStatus];
    }
}
