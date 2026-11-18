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
 * @property string $user_id
 * @property bool $is_owner
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Proposal $proposal
 */
#[Fillable(['edition_id', 'proposal_id', 'user_id', 'is_owner'])]
class ProposalSpeaker extends Model
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_owner' => 'boolean'];
    }
}
