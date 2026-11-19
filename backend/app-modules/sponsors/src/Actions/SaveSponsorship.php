<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Sponsors\Enums\SponsorshipStage;
use PHPeste\Sponsors\Events\SponsorshipStageChanged;
use PHPeste\Sponsors\Models\Sponsor;
use PHPeste\Sponsors\Models\Sponsorship;
use PHPeste\Sponsors\Models\SponsorTier;

/**
 * Cria ou edita um acordo. Sem valor, vale o preço da cota. Trocar a cota
 * de um acordo fechado ocupa vaga na cota nova (sem vaga, exige `confirm`).
 */
class SaveSponsorship
{
    public function __construct(
        private readonly EnsureTierSlot $slots,
        private readonly EnsureTeamMember $team,
        private readonly GenerateDeliverables $deliverables,
    ) {}

    /**
     * @param  array{sponsor_tier_id?: string|null, value_cents?: int|null, owner_id?: string|null, stage?: SponsorshipStage}  $attributes
     */
    public function handle(Sponsor $sponsor, ?Sponsorship $sponsorship, array $attributes, bool $confirm, ?string $actorId): Sponsorship
    {
        $editionId = $sponsor->edition_id;
        $tierId = array_key_exists('sponsor_tier_id', $attributes) ? $attributes['sponsor_tier_id'] : $sponsorship?->sponsor_tier_id;
        $tier = $tierId === null ? null : SponsorTier::query()->forEdition($editionId)->find($tierId);

        if ($tierId !== null && !$tier instanceof SponsorTier) {
            throw ValidationException::withMessages(['sponsor_tier_id' => 'Esta cota não é desta edição.']);
        }

        if (array_key_exists('owner_id', $attributes)) {
            $this->team->handle($editionId, $attributes['owner_id'], 'owner_id');
        }

        $tierChanged = !$sponsorship instanceof Sponsorship || $tierId !== $sponsorship->sponsor_tier_id;
        $value = $attributes['value_cents'] ?? ($tierChanged ? $tier?->price_cents : null);

        if (!$sponsorship instanceof Sponsorship && $value === null) {
            throw ValidationException::withMessages(['value_cents' => 'Informe o valor do acordo ou escolha uma cota.']);
        }

        /** @var Sponsorship */
        return DB::transaction(function () use ($sponsor, $sponsorship, $attributes, $tierId, $value, $confirm, $actorId): Sponsorship {
            if (!$sponsorship instanceof Sponsorship) {
                $stage = $attributes['stage'] ?? SponsorshipStage::Prospecting;

                if ($stage === SponsorshipStage::Paid || $stage === SponsorshipStage::Lost) {
                    throw ValidationException::withMessages(['stage' => 'Um acordo novo começa na prospecção, na negociação ou fechado.']);
                }

                $over = $stage->committed() && $this->slots->handle($sponsor->edition_id, $tierId, null, $confirm);

                $created = Sponsorship::create([
                    'edition_id' => $sponsor->edition_id,
                    'sponsor_id' => $sponsor->id,
                    'sponsor_tier_id' => $tierId,
                    'stage' => $stage,
                    'value_cents' => $value,
                    'owner_id' => $attributes['owner_id'] ?? null,
                    'closed_at' => $stage->committed() ? now() : null,
                ]);

                if ($stage->committed()) {
                    $this->deliverables->handle($created);
                }

                event(new SponsorshipStageChanged($created->edition_id, $created->id, null, $stage, $over, $actorId));

                return $created;
            }

            if ($sponsorship->stage->committed() && $tierId !== $sponsorship->sponsor_tier_id) {
                $this->slots->handle($sponsorship->edition_id, $tierId, $sponsorship->id, $confirm);
            }

            $sponsorship->update([
                'sponsor_tier_id' => $tierId,
                ...($value === null ? [] : ['value_cents' => $value]),
                ...(array_key_exists('owner_id', $attributes) ? ['owner_id' => $attributes['owner_id']] : []),
            ]);

            return $sponsorship;
        });
    }
}
