<?php

declare(strict_types=1);

namespace PHPeste\Finance\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use PHPeste\Finance\Models\Expense;
use PHPeste\Finance\Models\FinanceCategory;

/**
 * Categorias de despesa e o orçamento de cada uma. Na primeira leitura a
 * edição ganha um conjunto padrão, que a organização renomeia ou remove.
 */
class Categories
{
    /**
     * @return Collection<int, FinanceCategory>
     */
    public function forEdition(string $editionId): Collection
    {
        if (!FinanceCategory::query()->forEdition($editionId)->exists()) {
            foreach (FinanceCategory::DEFAULTS as $position => $name) {
                FinanceCategory::query()->forEdition($editionId)->firstOrCreate(['edition_id' => $editionId, 'name' => $name], ['position' => $position]);
            }
        }

        return FinanceCategory::query()->forEdition($editionId)->orderBy('position')->orderBy('name')->get();
    }

    /**
     * @param  array{name?: string, budget_cents?: int|null, position?: int}  $attributes
     */
    public function save(string $editionId, ?FinanceCategory $category, array $attributes): FinanceCategory
    {
        if (isset($attributes['name']) && FinanceCategory::query()->forEdition($editionId)->where('name', $attributes['name'])->when($category instanceof FinanceCategory, fn ($query) => $query->whereKeyNot($category?->id))->exists()) {
            throw ValidationException::withMessages(['name' => 'Já existe uma categoria com esse nome.']);
        }

        if (!$category instanceof FinanceCategory) {
            return FinanceCategory::create([
                'edition_id' => $editionId,
                'position' => (int) FinanceCategory::query()->forEdition($editionId)->max('position') + 1,
                ...$attributes,
            ]);
        }

        $category->update($attributes);

        return $category;
    }

    public function delete(FinanceCategory $category): void
    {
        if (Expense::query()->forEdition($category->edition_id)->where('finance_category_id', $category->id)->exists()) {
            throw ValidationException::withMessages(['category' => 'Esta categoria tem despesas. Mude a categoria delas antes de remover.']);
        }

        $category->delete();
    }
}
