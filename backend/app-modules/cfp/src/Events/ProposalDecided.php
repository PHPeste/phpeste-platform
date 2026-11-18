<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Cfp\Enums\ProposalDecision;

class ProposalDecided implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $proposalId,
        public readonly ProposalDecision $decision,
        public readonly ?ProposalDecision $previous,
        public readonly ?string $actorId,
    ) {}
}
