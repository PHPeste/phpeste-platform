<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Confirmations;

use PHPeste\Cfp\Decisions\DecisionBoard;
use PHPeste\Cfp\Enums\ProposalDecision;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\SpeakerConfirmation;

class WaitlistPromotions
{
    public function __construct(private readonly DecisionBoard $board) {}

    /**
     * @return list<array{track: array{id: string|null, name: string, slots: int}, occupied: int, open_slots: int, candidates: list<array{proposal: Proposal, final_score: float|null}>}>
     */
    public function suggestions(string $editionId): array
    {
        $confirmations = SpeakerConfirmation::query()->forEdition($editionId)->get()->keyBy('proposal_id');
        $result = [];

        foreach ($this->board->ranking($editionId) as $group) {
            $occupied = 0;
            $waiting = [];

            foreach ($group['proposals'] as $row) {
                $proposal = $row['proposal'];

                if ($proposal->decision === ProposalDecision::Accepted) {
                    $confirmation = $confirmations->get($proposal->id);
                    $occupied += $confirmation === null || $confirmation->status->holdsSlot() ? 1 : 0;
                } elseif ($proposal->decision === ProposalDecision::Waitlisted) {
                    $waiting[] = ['proposal' => $proposal, 'final_score' => $row['final_score']];
                }
            }

            $open = max(0, $group['track']['slots'] - $occupied);

            if ($open > 0 && $waiting !== []) {
                $result[] = [
                    'track' => $group['track'],
                    'occupied' => $occupied,
                    'open_slots' => $open,
                    'candidates' => array_slice($waiting, 0, $open),
                ];
            }
        }

        return $result;
    }
}
