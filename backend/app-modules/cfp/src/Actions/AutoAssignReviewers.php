<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use Illuminate\Support\Facades\DB;
use PHPeste\Cfp\Enums\ProposalStatus;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\ReviewAssignment;
use PHPeste\Cfp\Reviews\ConflictOfInterest;
use PHPeste\Community\Contracts\EditionMemberships;
use PHPeste\Community\Enums\EditionPermission;

class AutoAssignReviewers
{
    public function __construct(
        private readonly EditionMemberships $memberships,
        private readonly ConflictOfInterest $conflicts,
    ) {}

    /**
     * @return array{created: int, unfilled: list<int>}
     */
    public function handle(string $editionId, int $perProposal, ?string $assignedBy): array
    {
        $reviewerIds = $this->reviewerIds($editionId);

        return DB::transaction(function () use ($editionId, $perProposal, $assignedBy, $reviewerIds): array {
            $load = array_fill_keys($reviewerIds, 0);

            foreach (ReviewAssignment::query()->forEdition($editionId)->get() as $assignment) {
                if (array_key_exists($assignment->reviewer_id, $load)) {
                    $load[$assignment->reviewer_id]++;
                }
            }

            $created = 0;
            $unfilled = [];

            $proposals = Proposal::query()
                ->forEdition($editionId)
                ->where('status', ProposalStatus::Submitted)
                ->with(['speakers', 'assignments'])
                ->orderBy('number')
                ->get();

            foreach ($proposals as $proposal) {
                $assigned = $proposal->assignments->pluck('reviewer_id')->all();
                $missing = $perProposal - count($assigned);

                if ($missing <= 0) {
                    continue;
                }

                $candidates = array_filter(
                    $reviewerIds,
                    fn (string $reviewerId): bool => !in_array($reviewerId, $assigned, true) && $this->conflicts->reason($proposal, $reviewerId) === null,
                );

                usort($candidates, fn (string $a, string $b): int => [$load[$a], $a] <=> [$load[$b], $b]);

                foreach (array_slice($candidates, 0, $missing) as $reviewerId) {
                    ReviewAssignment::create(['edition_id' => $editionId, 'proposal_id' => $proposal->id, 'reviewer_id' => $reviewerId, 'assigned_by' => $assignedBy]);
                    $load[$reviewerId]++;
                    $created++;
                    $missing--;
                }

                if ($missing > 0) {
                    $unfilled[] = $proposal->number;
                }
            }

            return ['created' => $created, 'unfilled' => $unfilled];
        });
    }

    /**
     * @return list<string>
     */
    private function reviewerIds(string $editionId): array
    {
        return $this->memberships->usersWith($editionId, EditionPermission::ReviewProposals);
    }
}
