<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $review_id
 * @property string $criterion_id
 * @property int $score
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['review_id', 'criterion_id', 'score'])]
class ReviewScore extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['score' => 'integer'];
    }
}
