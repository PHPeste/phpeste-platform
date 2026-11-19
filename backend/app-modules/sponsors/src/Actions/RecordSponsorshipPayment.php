<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Sponsors\Enums\SponsorshipStage;
use PHPeste\Sponsors\Events\SponsorshipPaymentRecorded;
use PHPeste\Sponsors\Events\SponsorshipStageChanged;
use PHPeste\Sponsors\Models\Sponsorship;
use PHPeste\Sponsors\Models\SponsorshipPayment;

/**
 * Registra (ou remove) um pagamento recebido. Quando os pagamentos cobrem
 * o valor, o acordo fechado passa para pago sozinho; remover um pagamento
 * que deixa faltar dinheiro volta o acordo para fechado.
 */
class RecordSponsorshipPayment
{
    public function __construct(private readonly MoveSponsorship $move) {}

    public function record(Sponsorship $sponsorship, int $amountCents, Carbon $paidOn, ?string $note, ?string $actorId): SponsorshipPayment
    {
        if (!$sponsorship->stage->committed()) {
            throw ValidationException::withMessages(['sponsorship' => 'Feche o acordo antes de registrar pagamento.']);
        }

        /** @var SponsorshipPayment */
        return DB::transaction(function () use ($sponsorship, $amountCents, $paidOn, $note, $actorId): SponsorshipPayment {
            Sponsorship::query()->forEdition($sponsorship->edition_id)->whereKey($sponsorship->id)->lockForUpdate()->first();
            $paid = $this->move->paid($sponsorship);

            if ($paid + $amountCents > $sponsorship->value_cents) {
                throw ValidationException::withMessages(['amount_cents' => sprintf(
                    'Passa do valor do acordo: faltam só R$ %s.',
                    number_format(($sponsorship->value_cents - $paid) / 100, 2, ',', '.'),
                )]);
            }

            $payment = SponsorshipPayment::create([
                'edition_id' => $sponsorship->edition_id,
                'sponsorship_id' => $sponsorship->id,
                'amount_cents' => $amountCents,
                'paid_on' => $paidOn,
                'note' => $note,
                'recorded_by' => $actorId,
            ]);

            event(new SponsorshipPaymentRecorded($sponsorship->edition_id, $sponsorship->id, $payment->id, $amountCents, false, $actorId));

            if ($paid + $amountCents === $sponsorship->value_cents && $sponsorship->stage === SponsorshipStage::Closed) {
                $this->move->handle($sponsorship, SponsorshipStage::Paid, null, null, true, $actorId);
            }

            return $payment;
        });
    }

    public function remove(Sponsorship $sponsorship, SponsorshipPayment $payment, ?string $actorId): void
    {
        DB::transaction(function () use ($sponsorship, $payment, $actorId): void {
            $payment->delete();
            event(new SponsorshipPaymentRecorded($sponsorship->edition_id, $sponsorship->id, $payment->id, $payment->amount_cents, true, $actorId));

            if ($sponsorship->stage === SponsorshipStage::Paid && $this->move->paid($sponsorship) < $sponsorship->value_cents) {
                $sponsorship->update(['stage' => SponsorshipStage::Closed, 'paid_at' => null]);
                event(new SponsorshipStageChanged($sponsorship->edition_id, $sponsorship->id, SponsorshipStage::Paid, SponsorshipStage::Closed, false, $actorId));
            }
        });
    }
}
