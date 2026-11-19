<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Suppliers\Enums\ActivityKind;
use PHPeste\Suppliers\Enums\QuoteStatus;
use PHPeste\Suppliers\Enums\SupplierStatus;
use PHPeste\Suppliers\Events\SupplierStatusChanged;
use PHPeste\Suppliers\Models\EditionSupplier;
use PHPeste\Suppliers\Models\SupplierQuote;

/**
 * Contratar exige o valor: o de um orçamento aceito (`quote_id`, que vira
 * aceito) ou um valor informado. Recusar exige o motivo. Voltar para "a
 * contatar" ou "em orçamento" limpa o que foi decidido.
 */
class ChangeSupplierStatus
{
    public function __construct(private readonly RecordActivity $activity) {}

    public function handle(EditionSupplier $link, SupplierStatus $to, ?string $quoteId, ?int $contractedCents, ?string $reason, ?string $actorId): EditionSupplier
    {
        $from = $link->status;
        $quote = null;

        if ($to === SupplierStatus::Hired) {
            if ($quoteId !== null) {
                $quote = SupplierQuote::query()->forEdition($link->edition_id)->where('edition_supplier_id', $link->id)->whereKey($quoteId)->first();

                if (!$quote instanceof SupplierQuote) {
                    throw ValidationException::withMessages(['quote_id' => 'Este orçamento não é deste fornecedor.']);
                }
            }

            $contractedCents ??= $quote?->amount_cents;

            if ($contractedCents === null) {
                throw ValidationException::withMessages(['contracted_cents' => 'Escolha o orçamento aceito ou informe o valor contratado.']);
            }
        }

        if ($to === SupplierStatus::Declined && ($reason === null || mb_trim($reason) === '')) {
            throw ValidationException::withMessages(['reason' => 'Conte por que o fornecedor foi recusado.']);
        }

        DB::transaction(function () use ($link, $from, $to, $quote, $contractedCents, $reason, $actorId): void {
            SupplierQuote::query()->forEdition($link->edition_id)->where('edition_supplier_id', $link->id)
                ->where('status', QuoteStatus::Accepted->value)
                ->update(['status' => QuoteStatus::Received->value]);

            if ($quote instanceof SupplierQuote) {
                $quote->update(['status' => QuoteStatus::Accepted]);
            }

            $link->update([
                'status' => $to,
                'contracted_cents' => $to === SupplierStatus::Hired ? $contractedCents : null,
                'accepted_quote_id' => $quote?->id,
                'hired_at' => $to === SupplierStatus::Hired ? now() : null,
                'declined_at' => $to === SupplierStatus::Declined ? now() : null,
                'decline_reason' => $to === SupplierStatus::Declined ? mb_trim((string) $reason) : null,
            ]);

            $summary = match ($to) {
                SupplierStatus::Hired => sprintf('Contratado por R$ %s.', number_format((int) $contractedCents / 100, 2, ',', '.')),
                SupplierStatus::Declined => 'Recusado: '.mb_trim((string) $reason),
                default => sprintf('Status: %s.', $to->label()),
            };

            $this->activity->handle($link, ActivityKind::Status, $summary, $actorId);

            if ($from !== $to || $to === SupplierStatus::Hired) {
                event(new SupplierStatusChanged($link->edition_id, $link->id, $from, $to, $to === SupplierStatus::Hired ? $contractedCents : null, $actorId));
            }
        });

        return $link->refresh();
    }
}
