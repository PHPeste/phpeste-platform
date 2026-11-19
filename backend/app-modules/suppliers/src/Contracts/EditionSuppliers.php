<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Contracts;

/**
 * Fornecedores da edição para outro módulo (ex.: a despesa do Finance
 * aponta para um deles).
 */
interface EditionSuppliers
{
    /**
     * @param  list<string>|null  $ids  ids do vínculo (`edition_suppliers`); `null` = todos
     * @return array<string, array{id: string, name: string, status: string, contracted_cents: int|null}>
     */
    public function forEdition(string $editionId, ?array $ids = null): array;
}
