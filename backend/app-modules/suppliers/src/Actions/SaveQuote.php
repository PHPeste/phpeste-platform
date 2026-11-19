<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Suppliers\Enums\ActivityKind;
use PHPeste\Suppliers\Enums\QuoteStatus;
use PHPeste\Suppliers\Enums\SupplierStatus;
use PHPeste\Suppliers\Models\EditionSupplier;
use PHPeste\Suppliers\Models\SupplierQuote;

/**
 * Orçamento ou proposta recebida. O primeiro orçamento de um fornecedor
 * "a contatar" passa ele para "em orçamento".
 */
class SaveQuote
{
    public function __construct(private readonly RecordActivity $activity) {}

    public function record(EditionSupplier $link, int $amountCents, string $description, Carbon $receivedOn, ?Carbon $validUntil, ?string $actorId): SupplierQuote
    {
        /** @var SupplierQuote */
        return DB::transaction(function () use ($link, $amountCents, $description, $receivedOn, $validUntil, $actorId): SupplierQuote {
            $quote = SupplierQuote::create([
                'edition_id' => $link->edition_id,
                'edition_supplier_id' => $link->id,
                'amount_cents' => $amountCents,
                'description' => $description,
                'received_on' => $receivedOn,
                'valid_until' => $validUntil,
                'status' => QuoteStatus::Received,
                'recorded_by' => $actorId,
            ]);

            if ($link->status === SupplierStatus::ToContact) {
                $link->update(['status' => SupplierStatus::Quoting]);
            }

            $this->activity->handle($link, ActivityKind::Quote, sprintf('Orçamento recebido: R$ %s (%s).', number_format($amountCents / 100, 2, ',', '.'), $description), $actorId);

            return $quote;
        });
    }

    public function reject(SupplierQuote $quote): SupplierQuote
    {
        if ($quote->status === QuoteStatus::Accepted) {
            throw ValidationException::withMessages(['quote' => 'Este é o orçamento aceito do contrato. Mude o status do fornecedor antes.']);
        }

        $quote->update(['status' => QuoteStatus::Rejected]);

        return $quote;
    }

    public function delete(SupplierQuote $quote): void
    {
        if ($quote->status === QuoteStatus::Accepted) {
            throw ValidationException::withMessages(['quote' => 'Este é o orçamento aceito do contrato. Mude o status do fornecedor antes.']);
        }

        $quote->delete();
    }
}
