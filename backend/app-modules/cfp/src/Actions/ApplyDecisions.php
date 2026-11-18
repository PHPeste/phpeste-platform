<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use Illuminate\Support\Facades\DB;
use PHPeste\Cfp\Enums\ProposalDecision;
use PHPeste\Cfp\Enums\ProposalStatus;
use PHPeste\Cfp\Events\ProposalDecided;
use PHPeste\Cfp\Models\Proposal;

class ApplyDecisions
{
    /**
     * @param  array<string, ProposalDecision>  $decisions
     */
    public function handle(string $editionId, array $decisions, string $actorId): int
    {
        return DB::transaction(function () use ($editionId, $decisions, $actorId): int {
            $proposals = Proposal::query()
                ->forEdition($editionId)
                ->where('status', ProposalStatus::Submitted)
                ->whereIn('id', array_keys($decisions))
                ->lockForUpdate()
                ->get();

            $changed = 0;

            foreach ($proposals as $proposal) {
                $previous = $proposal->decision;
                $decision = $decisions[$proposal->id];

                if ($previous === $decision) {
                    continue;
                }

                $proposal->update(['decision' => $decision, 'decided_at' => now(), 'decided_by' => $actorId]);
                event(new ProposalDecided($editionId, $proposal->id, $decision, $previous, $actorId));
                $changed++;
            }

            return $changed;
        });
    }
}
