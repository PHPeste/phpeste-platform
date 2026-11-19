<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Suppliers\Enums\QuoteStatus;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $edition_supplier_id
 * @property int $amount_cents
 * @property string $description
 * @property Carbon $received_on
 * @property Carbon|null $valid_until
 * @property QuoteStatus $status
 * @property string|null $recorded_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'edition_supplier_id', 'amount_cents', 'description', 'received_on', 'valid_until', 'status', 'recorded_by'])]
#[Table(name: 'supplier_quotes')]
class SupplierQuote extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'received_on' => 'date',
            'valid_until' => 'date',
            'status' => QuoteStatus::class,
        ];
    }
}
