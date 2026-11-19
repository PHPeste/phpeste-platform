<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;
use PHPeste\Suppliers\Actions\SaveQuote;
use PHPeste\Suppliers\Http\Requests\RecordQuoteRequest;
use PHPeste\Suppliers\Http\Resources\SupplierPresenter;
use PHPeste\Suppliers\Models\SupplierQuote;

class SupplierQuoteController
{
    use Concerns;

    public function store(RecordQuoteRequest $request, SaveQuote $save): JsonResponse
    {
        $this->authorizeSuppliers();

        $quote = $save->record(
            $this->link($request),
            $request->integer('amount_cents'),
            $request->string('description')->trim()->toString(),
            Date::createFromFormat('Y-m-d', $request->string('received_on')->toString())?->startOfDay() ?? today(),
            $request->filled('valid_until') ? Date::createFromFormat('Y-m-d', $request->string('valid_until')->toString())?->startOfDay() : null,
            $this->actor($request),
        );

        return response()->json(['data' => $this->present($quote)], 201);
    }

    public function reject(Request $request, SaveQuote $save): JsonResponse
    {
        $this->authorizeSuppliers();

        return response()->json(['data' => $this->present($save->reject($this->quote($request)))]);
    }

    public function destroy(Request $request, SaveQuote $save): Response
    {
        $this->authorizeSuppliers();

        $save->delete($this->quote($request));

        return response()->noContent();
    }

    private function quote(Request $request): SupplierQuote
    {
        $id = $request->route('quote');
        abort_unless(is_string($id), 404);

        return SupplierQuote::query()->where('edition_supplier_id', $this->link($request)->id)->whereKey($id)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SupplierQuote $quote): array
    {
        return [
            'id' => $quote->id,
            'amount' => SupplierPresenter::money($quote->amount_cents),
            'description' => $quote->description,
            'received_on' => $quote->received_on->toDateString(),
            'valid_until' => $quote->valid_until?->toDateString(),
            'status' => $quote->status->value,
        ];
    }
}
