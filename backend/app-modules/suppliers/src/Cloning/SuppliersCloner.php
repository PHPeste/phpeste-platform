<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Cloning;

use PHPeste\Community\Contracts\EditionCloner;
use PHPeste\Suppliers\Enums\SupplierStatus;
use PHPeste\Suppliers\Models\EditionSupplier;

/**
 * Traz para a próxima edição os fornecedores contratados nesta, como "a
 * contatar". Orçamentos, valores, anexos e histórico ficam na edição antiga.
 */
class SuppliersCloner implements EditionCloner
{
    public function key(): string
    {
        return 'suppliers';
    }

    public function label(): string
    {
        return 'Fornecedores contratados (para contatar de novo)';
    }

    public function clone(string $sourceEditionId, string $targetEditionId): void
    {
        $hired = EditionSupplier::query()->forEdition($sourceEditionId)->where('status', SupplierStatus::Hired->value)->get(['supplier_id', 'owner_id']);

        foreach ($hired as $link) {
            EditionSupplier::create([
                'edition_id' => $targetEditionId,
                'supplier_id' => $link->supplier_id,
                'status' => SupplierStatus::ToContact,
            ]);
        }
    }
}
