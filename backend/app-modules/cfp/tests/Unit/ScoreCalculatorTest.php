<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use PHPeste\Cfp\Models\CfpCriterion;
use PHPeste\Cfp\Models\Review;
use PHPeste\Cfp\Models\ReviewScore;
use PHPeste\Cfp\Reviews\ScoreCalculator;

function criterion(string $id, int $weight): CfpCriterion
{
    return new CfpCriterion(['weight' => $weight])->forceFill(['id' => $id]);
}

function review(array $scores, string $status = Review::SUBMITTED): Review
{
    $review = new Review(['status' => $status]);
    $review->setRelation('scores', new Collection(array_map(
        fn (string $criterionId, int $score): ReviewScore => new ReviewScore(['criterion_id' => $criterionId, 'score' => $score]),
        array_keys($scores),
        $scores,
    )));

    return $review;
}

it('calcula a média ponderada pelos pesos dos critérios', function (): void {
    $criteria = new Collection([criterion('relevancia', 3), criterion('clareza', 1)]);

    expect(ScoreCalculator::review(review(['relevancia' => 5, 'clareza' => 1]), $criteria))->toBe(4.0)
        ->and(ScoreCalculator::proposal(new Collection([review(['relevancia' => 5, 'clareza' => 1]), review(['relevancia' => 3, 'clareza' => 3])]), $criteria))->toBe(3.5);
});

it('recalcula quando um critério muda de peso', function (): void {
    $reviews = new Collection([review(['relevancia' => 5, 'clareza' => 1])]);

    expect(ScoreCalculator::proposal($reviews, new Collection([criterion('relevancia', 3), criterion('clareza', 1)])))->toBe(4.0)
        ->and(ScoreCalculator::proposal($reviews, new Collection([criterion('relevancia', 1), criterion('clareza', 3)])))->toBe(2.0);
});

it('ignora critério removido e avaliação ainda em rascunho', function (): void {
    $criteria = new Collection([criterion('relevancia', 2)]);
    $reviews = new Collection([review(['relevancia' => 4, 'removido' => 1]), review(['relevancia' => 1], Review::DRAFT)]);

    expect(ScoreCalculator::proposal($reviews, $criteria))->toBe(4.0)
        ->and(ScoreCalculator::proposal(new Collection([review(['relevancia' => 1], Review::DRAFT)]), $criteria))->toBeNull();
});
