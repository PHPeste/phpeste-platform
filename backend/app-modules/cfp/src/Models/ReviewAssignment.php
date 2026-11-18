<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $proposal_id
 * @property string $reviewer_id
 * @property string|null $assigned_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Proposal $proposal
 */
#[Fillable(['edition_id', 'proposal_id', 'reviewer_id', 'assigned_by'])]
class ReviewAssignment extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return BelongsTo<Proposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }
}
