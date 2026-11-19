<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Access;

use PHPeste\Suppliers\Contracts\EditionSuppliers;
use PHPeste\Suppliers\Models\EditionSupplier;

class EloquentEditionSuppliers implements EditionSuppliers
{
    public function forEdition(string $editionId, ?array $ids = null): array
    {
        $found = [];

        $links = EditionSupplier::query()->forEdition($editionId)->with('supplier:id,name')
            ->when($ids !== null, fn ($query) => $query->whereKey($ids ?? []))
            ->get();

        foreach ($links as $link) {
            $found[$link->id] = ['id' => $link->id, 'name' => $link->supplier->name, 'status' => $link->status->value, 'contracted_cents' => $link->contracted_cents];
        }

        return $found;
    }
}
