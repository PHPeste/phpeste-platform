<?php

declare(strict_types=1);

namespace PHPeste\Finance\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Finance\Enums\ExpenseStatus;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $finance_category_id
 * @property string|null $edition_supplier_id
 * @property string $description
 * @property int $amount_cents
 * @property Carbon|null $due_on
 * @property Carbon|null $paid_on
 * @property ExpenseStatus $status
 * @property string|null $receipt_path
 * @property string|null $receipt_name
 * @property string|null $receipt_mime
 * @property int|null $receipt_size
 * @property string|null $notes
 * @property string|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read FinanceCategory $category
 */
#[Fillable(['edition_id', 'finance_category_id', 'edition_supplier_id', 'description', 'amount_cents', 'due_on', 'paid_on', 'status', 'receipt_path', 'receipt_name', 'receipt_mime', 'receipt_size', 'notes', 'created_by'])]
#[Hidden(['receipt_path'])]
#[Table(name: 'expenses')]
class Expense extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return BelongsTo<FinanceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'finance_category_id');
    }

    public function overdue(): bool
    {
        return $this->status === ExpenseStatus::Pending && $this->due_on !== null && $this->due_on->lt(today());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExpenseStatus::class,
            'amount_cents' => 'integer',
            'due_on' => 'date',
            'paid_on' => 'date',
            'receipt_size' => 'integer',
        ];
    }
}
