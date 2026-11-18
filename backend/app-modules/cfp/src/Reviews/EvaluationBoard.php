<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Reviews;

use Illuminate\Support\Collection;
use PHPeste\Cfp\Enums\ProposalStatus;
use PHPeste\Cfp\Models\CfpCriterion;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\Review;

class EvaluationBoard
{
    public const string PENDING = 'pending';

    public const string REVIEWED = 'reviewed';

    public const string TIE = 'tie';

    /**
     * @return array{criteria: Collection<int, CfpCriterion>, rows: list<array{proposal: Proposal, final_score: float|null, review_status: string, assigned: int, submitted: int, criterion_averages: array<string, float>}>}
     */
    public function build(string $editionId): array
    {
        $criteria = CfpCriterion::query()->forEdition($editionId)->orderBy('position')->get();

        $proposals = Proposal::query()
            ->forEdition($editionId)
            ->where('status', ProposalStatus::Submitted)
            ->fromCfp()
            ->with(['speakers', 'track', 'assignments', 'reviews.scores'])
            ->orderBy('number')
            ->get();

        $rows = array_values($proposals->map(function (Proposal $proposal) use ($criteria): array {
            $submitted = $proposal->reviews->filter(fn (Review $review): bool => $review->isSubmitted())->count();
            $assigned = $proposal->assignments->count();

            return [
                'proposal' => $proposal,
                'final_score' => ScoreCalculator::proposal($proposal->reviews, $criteria),
                'review_status' => $submitted > 0 && $submitted >= $assigned ? self::REVIEWED : self::PENDING,
                'assigned' => $assigned,
                'submitted' => $submitted,
                'criterion_averages' => ScoreCalculator::criterionAverages($proposal->reviews),
            ];
        })->all());

        return ['criteria' => $criteria, 'rows' => $this->markTies($rows)];
    }

    /**
     * @param  list<array{proposal: Proposal, final_score: float|null, review_status: string, assigned: int, submitted: int, criterion_averages: array<string, float>}>  $rows
     * @return list<array{proposal: Proposal, final_score: float|null, review_status: string, assigned: int, submitted: int, criterion_averages: array<string, float>}>
     */
    private function markTies(array $rows): array
    {
        $counts = [];

        foreach ($rows as $row) {
            if ($row['review_status'] === self::REVIEWED && $row['final_score'] !== null) {
                $key = ($row['proposal']->track_id ?? '').'|'.number_format($row['final_score'], 2);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        foreach ($rows as $index => $row) {
            if ($row['review_status'] === self::REVIEWED && $row['final_score'] !== null) {
                $key = ($row['proposal']->track_id ?? '').'|'.number_format($row['final_score'], 2);

                if ($counts[$key] > 1) {
                    $rows[$index]['review_status'] = self::TIE;
                }
            }
        }

        return $rows;
    }
}
