<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Sponsors\Enums\SponsorshipStage;
use PHPeste\Sponsors\Events\SponsorshipStageChanged;
use PHPeste\Sponsors\Models\Sponsorship;
use PHPeste\Sponsors\Models\SponsorshipPayment;

/**
 * Move o acordo no funil (arrastar o card). Entrar em fechado ou pago
 * ocupa vaga da cota (sem vaga, exige `confirm`) e, na primeira vez, cria
 * as entregas das contrapartidas. Pago exige o valor inteiro registrado.
 */
class MoveSponsorship
{
    public function __construct(
        private readonly EnsureTierSlot $slots,
        private readonly GenerateDeliverables $deliverables,
    ) {}

    public function handle(Sponsorship $sponsorship, SponsorshipStage $to, ?int $position, ?string $lostReason, bool $confirm, ?string $actorId): Sponsorship
    {
        $from = $sponsorship->stage;

        if ($to === SponsorshipStage::Lost && ($lostReason === null || mb_trim($lostReason) === '')) {
            throw ValidationException::withMessages(['lost_reason' => 'Conte por que o acordo foi perdido.']);
        }

        DB::transaction(function () use ($sponsorship, $from, $to, $position, $lostReason, $confirm, $actorId): void {
            $over = false;

            if ($to->committed() && !$from->committed()) {
                $over = $this->slots->handle($sponsorship->edition_id, $sponsorship->sponsor_tier_id, $sponsorship->id, $confirm);
            }

            if ($to === SponsorshipStage::Paid && $this->paid($sponsorship) < $sponsorship->value_cents) {
                throw ValidationException::withMessages(['stage' => sprintf(
                    'Faltam R$ %s para este acordo estar pago. Registre o pagamento primeiro.',
                    number_format(($sponsorship->value_cents - $this->paid($sponsorship)) / 100, 2, ',', '.'),
                )]);
            }

            $changes = ['stage' => $to, ...($position === null ? [] : ['position' => $position])];

            if ($from !== $to) {
                $changes = [
                    ...$changes,
                    'closed_at' => $to->committed() ? ($sponsorship->closed_at ?? now()) : null,
                    'paid_at' => $to === SponsorshipStage::Paid ? now() : null,
                    'lost_at' => $to === SponsorshipStage::Lost ? now() : null,
                    'lost_reason' => $to === SponsorshipStage::Lost ? mb_trim((string) $lostReason) : null,
                ];
            }

            $sponsorship->update($changes);

            if ($to->committed()) {
                $this->deliverables->handle($sponsorship);
            }

            if ($from !== $to) {
                event(new SponsorshipStageChanged($sponsorship->edition_id, $sponsorship->id, $from, $to, $over, $actorId));
            }
        });

        return $sponsorship->refresh();
    }

    public function paid(Sponsorship $sponsorship): int
    {
        return (int) SponsorshipPayment::query()->forEdition($sponsorship->edition_id)->where('sponsorship_id', $sponsorship->id)->sum('amount_cents');
    }
}
