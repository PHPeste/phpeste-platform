<?php

declare(strict_types=1);

namespace PHPeste\Finance\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Finance\Actions\Categories;
use PHPeste\Finance\Actions\SaveExpense;
use PHPeste\Finance\Enums\ExpenseStatus;
use PHPeste\Finance\Export\FinanceSpreadsheet;
use PHPeste\Finance\Export\XlsxWriter;
use PHPeste\Finance\Http\Requests\SaveCategoryRequest;
use PHPeste\Finance\Http\Requests\SaveExpenseRequest;
use PHPeste\Finance\Http\Requests\UploadReceiptRequest;
use PHPeste\Finance\Models\Expense;
use PHPeste\Finance\Models\FinanceCategory;
use PHPeste\Finance\Reports\FinanceReport;
use PHPeste\Suppliers\Contracts\EditionSuppliers;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Finanças da edição. Tudo exige `finance.manage` (organizador e
 * financeiro). Valores em centavos, como no resto da API.
 */
class FinanceController
{
    public function summary(CurrentEdition $edition, FinanceReport $report): JsonResponse
    {
        $this->authorize();

        return response()->json(['data' => $report->summary((string) $edition->id())]);
    }

    public function categories(CurrentEdition $edition, Categories $categories): JsonResponse
    {
        $this->authorize();

        return response()->json(['data' => $categories->forEdition((string) $edition->id())->map(fn (FinanceCategory $category): array => $this->category($category))->all()]);
    }

    public function storeCategory(SaveCategoryRequest $request, CurrentEdition $edition, Categories $categories): JsonResponse
    {
        $this->authorize();

        return response()->json(['data' => $this->category($categories->save((string) $edition->id(), null, $request->category()))], 201);
    }

    public function updateCategory(SaveCategoryRequest $request, CurrentEdition $edition, Categories $categories): JsonResponse
    {
        $this->authorize();

        $category = $categories->save((string) $edition->id(), $this->find($request, 'category', FinanceCategory::class), $request->category());

        return response()->json(['data' => $this->category($category)]);
    }

    public function destroyCategory(Request $request, Categories $categories): Response
    {
        $this->authorize();

        $categories->delete($this->find($request, 'category', FinanceCategory::class));

        return response()->noContent();
    }

    /**
     * Fornecedores da edição para escolher na despesa.
     */
    public function suppliers(CurrentEdition $edition, EditionSuppliers $suppliers): JsonResponse
    {
        $this->authorize();

        return response()->json(['data' => array_values($suppliers->forEdition((string) $edition->id()))]);
    }

    /**
     * Filtros: `status`, `finance_category_id`, `edition_supplier_id`, `overdue=1`.
     */
    public function expenses(Request $request, CurrentEdition $edition, EditionSuppliers $suppliers): JsonResponse
    {
        $this->authorize();

        $status = ExpenseStatus::tryFrom((string) $request->query('status'));
        $category = $request->query('finance_category_id');
        $supplier = $request->query('edition_supplier_id');

        $expenses = Expense::query()->with('category:id,name')
            ->when($status !== null, fn ($query) => $query->where('status', $status?->value))
            ->when(is_string($category) && $category !== '', fn ($query) => $query->where('finance_category_id', $category))
            ->when(is_string($supplier) && $supplier !== '', fn ($query) => $query->where('edition_supplier_id', $supplier))
            ->when($request->boolean('overdue'), fn ($query) => $query->where('status', ExpenseStatus::Pending->value)->whereDate('due_on', '<', today()->toDateString()))
            ->orderByRaw('due_on is null')->oldest('due_on')->oldest()->orderBy('id')
            ->get();
        $names = $suppliers->forEdition((string) $edition->id());

        return response()->json([
            'data' => $expenses->map(fn (Expense $expense): array => $this->expense($expense, $names))->all(),
            'totals' => [
                'count' => $expenses->count(),
                'amount' => (int) $expenses->where('status', '!=', ExpenseStatus::Cancelled)->sum('amount_cents'),
                'paid' => (int) $expenses->where('status', ExpenseStatus::Paid)->sum('amount_cents'),
                'pending' => (int) $expenses->where('status', ExpenseStatus::Pending)->sum('amount_cents'),
            ],
        ]);
    }

    public function storeExpense(SaveExpenseRequest $request, CurrentEdition $edition, SaveExpense $save, EditionSuppliers $suppliers): JsonResponse
    {
        $this->authorize();

        $expense = $save->handle((string) $edition->id(), null, $request->expense(), $this->actor($request));

        return response()->json(['data' => $this->expense($expense->load('category'), $suppliers->forEdition($expense->edition_id))], 201);
    }

    public function updateExpense(SaveExpenseRequest $request, CurrentEdition $edition, SaveExpense $save, EditionSuppliers $suppliers): JsonResponse
    {
        $this->authorize();

        $expense = $save->handle((string) $edition->id(), $this->find($request, 'expense', Expense::class), $request->expense(), $this->actor($request));

        return response()->json(['data' => $this->expense($expense->load('category'), $suppliers->forEdition($expense->edition_id))]);
    }

    public function destroyExpense(Request $request, SaveExpense $save): Response
    {
        $this->authorize();

        $save->delete($this->find($request, 'expense', Expense::class), $this->actor($request));

        return response()->noContent();
    }

    public function uploadReceipt(UploadReceiptRequest $request, SaveExpense $save, EditionSuppliers $suppliers): JsonResponse
    {
        $this->authorize();

        $expense = $save->attachReceipt($this->find($request, 'expense', Expense::class), $request->upload());

        return response()->json(['data' => $this->expense($expense->load('category'), $suppliers->forEdition($expense->edition_id))]);
    }

    public function receiptUrl(Request $request): JsonResponse
    {
        $this->authorize();

        $expense = $this->find($request, 'expense', Expense::class);
        abort_if($expense->receipt_path === null, 404);
        $expires = now()->addMinutes(max(1, (int) config('finance.receipts.url_minutes', 10)));

        return response()->json(['data' => [
            'url' => URL::temporarySignedRoute('finance.receipts.download', $expires, ['expense' => $expense->id]),
            'expires_at' => $expires->clone()->utc()->toIso8601ZuluString(),
        ]]);
    }

    /**
     * Sem login: a assinatura com expiração é a autorização.
     */
    public function downloadReceipt(Request $request): StreamedResponse
    {
        $id = $request->route('expense');
        abort_unless(is_string($id), 404);

        $expense = Expense::query()->withoutGlobalScopes()->whereKey($id)->firstOrFail();
        $disk = Storage::disk(SaveExpense::disk());
        abort_unless($expense->receipt_path !== null && $disk->exists($expense->receipt_path), 404);

        return $disk->download($expense->receipt_path, (string) $expense->receipt_name, [
            'Content-Type' => (string) $expense->receipt_mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * `format=xlsx` (padrão): resumo, receita por conta, orçamento e
     * despesas em abas. `format=csv`: só as despesas, separadas por ponto e
     * vírgula, que é o que o Excel em português abre direto.
     */
    public function export(Request $request, CurrentEdition $edition, FinanceSpreadsheet $spreadsheet): StreamedResponse|BinaryFileResponse
    {
        $this->authorize();

        $editionId = (string) $edition->id();
        $date = now()->format('Y-m-d');

        if ($request->query('format') === 'csv') {
            $rows = $spreadsheet->expenses($editionId);

            return response()->streamDownload(function () use ($rows): void {
                $out = fopen('php://output', 'wb');

                if ($out === false) {
                    return;
                }

                fwrite($out, "\xEF\xBB\xBF");

                foreach ($rows as $row) {
                    fputcsv($out, array_map(fn (string|int|float|null $value): string => is_float($value) ? number_format($value, 2, ',', '') : (string) $value, $row), ';', '"', '');
                }

                fclose($out);
            }, "despesas-{$date}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $path = tempnam(sys_get_temp_dir(), 'financas-');
        abort_if($path === false, 500);
        XlsxWriter::write($spreadsheet->sheets($editionId), $path);

        return response()->download($path, "financas-{$date}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    private function authorize(): void
    {
        Gate::authorize(EditionPermission::ManageFinance->value);
    }

    private function actor(Request $request): ?string
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_string($id) ? $id : null;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $model
     * @return TModel
     */
    private function find(Request $request, string $parameter, string $model): Model
    {
        $id = $request->route($parameter);
        abort_unless(is_string($id), 404);

        return $model::query()->whereKey($id)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function category(FinanceCategory $category): array
    {
        return ['id' => $category->id, 'name' => $category->name, 'budget' => $category->budget_cents, 'position' => $category->position];
    }

    /**
     * @param  array<string, array{id: string, name: string, status: string, contracted_cents: int|null}>  $suppliers
     * @return array<string, mixed>
     */
    private function expense(Expense $expense, array $suppliers): array
    {
        $supplier = $expense->edition_supplier_id === null ? null : ($suppliers[$expense->edition_supplier_id] ?? null);

        return [
            'id' => $expense->id,
            'description' => $expense->description,
            'category' => ['id' => $expense->category->id, 'name' => $expense->category->name],
            'supplier' => $supplier === null ? null : ['id' => $supplier['id'], 'name' => $supplier['name']],
            'amount' => $expense->amount_cents,
            'due_on' => $expense->due_on?->toDateString(),
            'paid_on' => $expense->paid_on?->toDateString(),
            'status' => $expense->status->value,
            'status_label' => $expense->overdue() ? 'Atrasada' : $expense->status->label(),
            'overdue' => $expense->overdue(),
            'notes' => $expense->notes,
            'receipt' => $expense->receipt_path === null ? null : ['name' => $expense->receipt_name, 'mime_type' => $expense->receipt_mime, 'size_bytes' => $expense->receipt_size],
        ];
    }
}
