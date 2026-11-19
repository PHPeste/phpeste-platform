<?php

declare(strict_types=1);

namespace PHPeste\Finance\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * Categoria de despesa com o orçamento previsto (nulo = sem orçamento).
 *
 * @property string $id
 * @property string $edition_id
 * @property string $name
 * @property int|null $budget_cents
 * @property int $position
 */
#[Fillable(['edition_id', 'name', 'budget_cents', 'position'])]
#[Table(name: 'finance_categories')]
class FinanceCategory extends Model
{
    use BelongsToEdition;
    use HasUuids;

    public const array DEFAULTS = ['Local', 'Alimentação', 'Som, luz e vídeo', 'Gráfica e brindes', 'Viagem de palestrantes', 'Hospedagem', 'Acessibilidade', 'Outros'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['budget_cents' => 'integer', 'position' => 'integer'];
    }
}
