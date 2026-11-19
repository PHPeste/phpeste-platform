<?php

declare(strict_types=1);

namespace PHPeste\Finance\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPeste\Finance\Enums\ExpenseStatus;
use PHPeste\Finance\Events\ExpenseChanged;
use PHPeste\Finance\Models\Expense;
use PHPeste\Finance\Models\FinanceCategory;
use PHPeste\Suppliers\Contracts\EditionSuppliers;

/**
 * Despesa com categoria, fornecedor (opcional, da lista de fornecedores da
 * edição), valor, vencimento, status e comprovante. Paga exige a data do
 * pagamento.
 */
class SaveExpense
{
    public const array RECEIPT_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'xml'];

    public function __construct(private readonly EditionSuppliers $suppliers) {}

    public static function disk(): string
    {
        return (string) config('finance.receipts.disk', 'local');
    }

    /**
     * @param  array{finance_category_id?: string, edition_supplier_id?: string|null, description?: string, amount_cents?: int, due_on?: Carbon|null, paid_on?: Carbon|null, status?: ExpenseStatus, notes?: string|null}  $attributes
     */
    public function handle(string $editionId, ?Expense $expense, array $attributes, ?string $actorId): Expense
    {
        if (isset($attributes['finance_category_id']) && !FinanceCategory::query()->forEdition($editionId)->whereKey($attributes['finance_category_id'])->exists()) {
            throw ValidationException::withMessages(['finance_category_id' => 'Esta categoria não é desta edição.']);
        }

        if (($attributes['edition_supplier_id'] ?? null) !== null && $this->suppliers->forEdition($editionId, [$attributes['edition_supplier_id']]) === []) {
            throw ValidationException::withMessages(['edition_supplier_id' => 'Este fornecedor não está nesta edição.']);
        }

        $status = $attributes['status'] ?? $expense->status ?? ExpenseStatus::Pending;
        $paidOn = array_key_exists('paid_on', $attributes) ? $attributes['paid_on'] : $expense?->paid_on;

        if ($status === ExpenseStatus::Paid && $paidOn === null) {
            if (isset($attributes['status'])) {
                $paidOn = today();
            } else {
                throw ValidationException::withMessages(['paid_on' => 'Informe quando a despesa foi paga.']);
            }
        }

        $attributes['status'] = $status;
        $attributes['paid_on'] = $status === ExpenseStatus::Paid ? $paidOn : null;

        /** @var Expense */
        return DB::transaction(function () use ($editionId, $expense, $attributes, $actorId): Expense {
            if (!$expense instanceof Expense) {
                if (!isset($attributes['finance_category_id'], $attributes['description'], $attributes['amount_cents'])) {
                    throw ValidationException::withMessages(['description' => 'Informe categoria, descrição e valor.']);
                }

                $created = Expense::create(['edition_id' => $editionId, 'created_by' => $actorId, ...$attributes]);
                event(new ExpenseChanged($editionId, $created->id, 'created', null, $this->snapshot($created), $actorId));

                return $created;
            }

            $before = $this->snapshot($expense);
            $expense->update($attributes);
            event(new ExpenseChanged($editionId, $expense->id, 'updated', $before, $this->snapshot($expense), $actorId));

            return $expense;
        });
    }

    public function attachReceipt(Expense $expense, UploadedFile $file): Expense
    {
        $extension = mb_strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, self::RECEIPT_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['file' => 'Envie o comprovante em '.implode(', ', self::RECEIPT_EXTENSIONS).'.']);
        }

        $old = $expense->receipt_path;
        $path = sprintf('finance/%s/%s.%s', $expense->edition_id, Str::uuid7()->toString(), $extension);
        $content = $file->getContent();
        Storage::disk(self::disk())->put($path, $content);

        $expense->update([
            'receipt_path' => $path,
            'receipt_name' => mb_substr($file->getClientOriginalName(), 0, 191),
            'receipt_mime' => $file->getMimeType() ?? 'application/octet-stream',
            'receipt_size' => mb_strlen($content, '8bit'),
        ]);

        if ($old !== null) {
            Storage::disk(self::disk())->delete($old);
        }

        return $expense;
    }

    public function delete(Expense $expense, ?string $actorId): void
    {
        $path = $expense->receipt_path;
        $before = $this->snapshot($expense);

        DB::transaction(function () use ($expense, $before, $actorId): void {
            $expense->delete();
            event(new ExpenseChanged($expense->edition_id, $expense->id, 'deleted', $before, null, $actorId));
        });

        if ($path !== null) {
            Storage::disk(self::disk())->delete($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Expense $expense): array
    {
        return [
            'description' => $expense->description,
            'amount_cents' => $expense->amount_cents,
            'status' => $expense->status->value,
            'due_on' => $expense->due_on?->toDateString(),
            'paid_on' => $expense->paid_on?->toDateString(),
            'finance_category_id' => $expense->finance_category_id,
        ];
    }
}
