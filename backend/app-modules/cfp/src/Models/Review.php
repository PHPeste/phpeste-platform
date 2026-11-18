<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $proposal_id
 * @property string $reviewer_id
 * @property string|null $comment
 * @property string $status
 * @property Carbon|null $submitted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Proposal $proposal
 * @property-read Collection<int, ReviewScore> $scores
 */
#[Fillable(['edition_id', 'proposal_id', 'reviewer_id', 'comment', 'status', 'submitted_at'])]
class Review extends Model
{
    use BelongsToEdition;
    use HasUuids;

    public const string DRAFT = 'draft';

    public const string SUBMITTED = 'submitted';

    protected $attributes = ['status' => 'draft'];

    public function isSubmitted(): bool
    {
        return $this->status === self::SUBMITTED;
    }

    /**
     * @return BelongsTo<Proposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * @return HasMany<ReviewScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(ReviewScore::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }
}
