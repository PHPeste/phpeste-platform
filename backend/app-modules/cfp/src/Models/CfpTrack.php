<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use PHPeste\Cfp\Enums\ProposalOrigin;
use PHPeste\Cfp\Enums\ProposalStatus;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $name
 * @property int $slots
 * @property int $position
 * @property int|null $proposals_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'name', 'slots', 'position'])]
#[Table(name: 'cfp_tracks')]
class CfpTrack extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return HasMany<Proposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class, 'track_id');
    }

    public function submittedProposalsCount(): int
    {
        return $this->proposals_count ?? $this->proposals()->where('status', ProposalStatus::Submitted)->where('origin', ProposalOrigin::Cfp)->count();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['slots' => 'integer', 'position' => 'integer'];
    }
}
