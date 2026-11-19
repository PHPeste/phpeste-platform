<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Suppliers\Enums\ActivityKind;
use PHPeste\Suppliers\Enums\ContactChannel;

/**
 * Histórico do fornecedor na edição: anotações de contato feitas pela
 * equipe e registros automáticos (status, orçamento, anexo).
 *
 * @property string $id
 * @property string $edition_id
 * @property string $edition_supplier_id
 * @property ActivityKind $kind
 * @property ContactChannel|null $channel
 * @property string $summary
 * @property string|null $user_id
 * @property Carbon $happened_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'edition_supplier_id', 'kind', 'channel', 'summary', 'user_id', 'happened_at'])]
#[Table(name: 'supplier_activities')]
class SupplierActivity extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => ActivityKind::class, 'channel' => ContactChannel::class, 'happened_at' => 'datetime'];
    }
}
