<?php

declare(strict_types=1);

namespace PHPeste\Finance\Cloning;

use PHPeste\Community\Contracts\EditionCloner;
use PHPeste\Finance\Models\FinanceCategory;

/**
 * Leva as categorias e o orçamento de cada uma para a próxima edição.
 * Despesas ficam na edição antiga.
 */
class FinanceCloner implements EditionCloner
{
    public function key(): string
    {
        return 'finance';
    }

    public function label(): string
    {
        return 'Categorias e orçamento de despesas';
    }

    public function clone(string $sourceEditionId, string $targetEditionId): void
    {
        foreach (FinanceCategory::query()->forEdition($sourceEditionId)->orderBy('position')->get() as $category) {
            FinanceCategory::create([
                'edition_id' => $targetEditionId,
                'name' => $category->name,
                'budget_cents' => $category->budget_cents,
                'position' => $category->position,
            ]);
        }
    }
}
