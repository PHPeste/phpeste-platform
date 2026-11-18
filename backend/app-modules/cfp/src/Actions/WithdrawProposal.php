<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use PHPeste\Cfp\Enums\ProposalStatus;
use PHPeste\Cfp\Models\Proposal;

class WithdrawProposal
{
    public function handle(Proposal $proposal): Proposal
    {
        if ($proposal->status !== ProposalStatus::Withdrawn) {
            $proposal->update(['status' => ProposalStatus::Withdrawn, 'withdrawn_at' => now()]);
        }

        return $proposal->load('speakers');
    }
}
