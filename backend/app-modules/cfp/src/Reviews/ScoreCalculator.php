<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Reviews;

use Illuminate\Support\Collection;
use PHPeste\Cfp\Models\CfpCriterion;
use PHPeste\Cfp\Models\Review;

final class ScoreCalculator
{
    /**
     * @param  Collection<int, CfpCriterion>  $criteria
     */
    public static function review(Review $review, Collection $criteria): ?float
    {
        $weights = $criteria->mapWithKeys(fn (CfpCriterion $criterion): array => [$criterion->id => $criterion->weight]);
        $weighted = 0;
        $totalWeight = 0;

        foreach ($review->scores as $score) {
            $weight = $weights->get($score->criterion_id);

            if ($weight === null) {
                continue;
            }

            $weighted += $score->score * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight === 0 ? null : $weighted / $totalWeight;
    }

    /**
     * @param  Collection<int, Review>  $reviews
     * @param  Collection<int, CfpCriterion>  $criteria
     */
    public static function proposal(Collection $reviews, Collection $criteria): ?float
    {
        $scores = $reviews
            ->filter(fn (Review $review): bool => $review->isSubmitted())
            ->map(fn (Review $review): ?float => self::review($review, $criteria))
            ->filter(fn (?float $score): bool => $score !== null);

        return $scores->isEmpty() ? null : round((float) $scores->avg(), 2);
    }

    /**
     * @param  Collection<int, Review>  $reviews
     * @return array<string, float>
     */
    public static function criterionAverages(Collection $reviews): array
    {
        $grouped = [];

        foreach ($reviews as $review) {
            if (!$review->isSubmitted()) {
                continue;
            }

            foreach ($review->scores as $score) {
                $grouped[$score->criterion_id][] = $score->score;
            }
        }

        return array_map(fn (array $scores): float => round(array_sum($scores) / count($scores), 2), $grouped);
    }
}
