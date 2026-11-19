<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Sponsors\Enums\SponsorshipStage;
use PHPeste\Sponsors\Models\Sponsorship;
use PHPeste\Sponsors\Models\SponsorTier;

/**
 * Confere se a cota ainda tem vaga para mais um acordo fechado. Sem vaga,
 * só passa com `confirm`. Roda dentro da transação de quem fecha o acordo,
 * com a cota travada, para dois fechamentos ao mesmo tempo não passarem
 * os dois pela última vaga sem confirmação.
 */
class EnsureTierSlot
{
    /**
     * @return bool `true` quando o acordo passa do número de vagas
     */
    public function handle(string $editionId, ?string $tierId, ?string $exceptSponsorshipId, bool $confirm): bool
    {
        if ($tierId === null) {
            return false;
        }

        $tier = SponsorTier::query()->forEdition($editionId)->whereKey($tierId)->lockForUpdate()->first();

        if (!$tier instanceof SponsorTier || $tier->slots === null) {
            return false;
        }

        $taken = Sponsorship::query()->forEdition($editionId)
            ->where('sponsor_tier_id', $tier->id)
            ->whereIn('stage', SponsorshipStage::committedValues())
            ->when($exceptSponsorshipId !== null, fn ($query) => $query->whereKeyNot($exceptSponsorshipId))
            ->count();

        if ($taken < $tier->slots) {
            return false;
        }

        if (!$confirm) {
            throw ValidationException::withMessages(['confirm' => sprintf(
                'A cota %s tem %d %s e %s. Confirme para fechar mais um acordo nela.',
                $tier->name,
                $tier->slots,
                $tier->slots === 1 ? 'vaga' : 'vagas',
                $taken === 1 ? '1 já está fechada' : sprintf('%d já estão fechadas', $taken),
            )]);
        }

        return true;
    }
}
