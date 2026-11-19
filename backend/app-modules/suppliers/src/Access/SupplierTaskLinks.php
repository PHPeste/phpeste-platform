<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Access;

use PHPeste\Suppliers\Models\EditionSupplier;
use PHPeste\Tasks\Contracts\TaskLinkProvider;

/**
 * Deixa uma tarefa apontar para um fornecedor desta edição.
 */
class SupplierTaskLinks implements TaskLinkProvider
{
    public function key(): string
    {
        return 'supplier';
    }

    public function label(): string
    {
        return 'Fornecedor';
    }

    public function names(string $editionId, array $ids): array
    {
        return EditionSupplier::query()->forEdition($editionId)->with('supplier:id,name')->whereKey($ids)->get()
            ->mapWithKeys(fn (EditionSupplier $link): array => [$link->id => $link->supplier->name])->all();
    }

    public function options(string $editionId): array
    {
        return array_values(EditionSupplier::query()->forEdition($editionId)->with('supplier:id,name')->get()
            ->map(fn (EditionSupplier $link): array => ['id' => $link->id, 'name' => $link->supplier->name])
            ->sortBy('name')->all());
    }
}
