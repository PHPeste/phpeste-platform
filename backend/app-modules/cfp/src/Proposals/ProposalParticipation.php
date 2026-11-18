<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Proposals;

use Illuminate\Contracts\Database\Query\Builder;
use PHPeste\Cfp\Enums\ProposalStatus;
use PHPeste\Cfp\Models\ProposalSpeaker;
use PHPeste\Identity\Contracts\EditionParticipation;

class ProposalParticipation implements EditionParticipation
{
    public function participates(string $editionId, string $userId): bool
    {
        return ProposalSpeaker::query()
            ->forEdition($editionId)
            ->where('user_id', $userId)
            ->whereHas('proposal', fn (Builder $query) => $query->withoutGlobalScopes()->where('status', '!=', ProposalStatus::Draft))
            ->exists();
    }
}
