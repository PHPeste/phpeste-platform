<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Cloning;

use PHPeste\Community\Contracts\EditionCloner;
use PHPeste\Sponsors\Models\SponsorSetting;
use PHPeste\Sponsors\Models\SponsorTier;

/**
 * Leva as cotas (valor, vagas, contrapartidas) e a meta para a próxima
 * edição. Empresas, acordos e contatos não vão: são da negociação deste ano.
 */
class SponsorTiersCloner implements EditionCloner
{
    public function key(): string
    {
        return 'sponsor_tiers';
    }

    public function label(): string
    {
        return 'Cotas e meta de patrocínio';
    }

    public function clone(string $sourceEditionId, string $targetEditionId): void
    {
        foreach (SponsorTier::query()->forEdition($sourceEditionId)->orderBy('position')->get() as $tier) {
            SponsorTier::create([
                'edition_id' => $targetEditionId,
                'name' => $tier->name,
                'price_cents' => $tier->price_cents,
                'slots' => $tier->slots,
                'benefits' => $tier->benefits,
                'position' => $tier->position,
            ]);
        }

        $goal = SponsorSetting::query()->forEdition($sourceEditionId)->value('goal_cents');

        if ($goal !== null) {
            SponsorSetting::create(['edition_id' => $targetEditionId, 'goal_cents' => $goal]);
        }
    }
}
