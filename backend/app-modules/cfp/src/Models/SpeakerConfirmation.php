<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use PHPeste\Cfp\Enums\ConfirmationStatus;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $proposal_id
 * @property ConfirmationStatus $status
 * @property Carbon $deadline_at
 * @property Carbon|null $responded_at
 * @property Carbon|null $reminded_at
 * @property string|null $reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Proposal $proposal
 */
#[Fillable(['edition_id', 'proposal_id', 'status', 'deadline_at', 'responded_at', 'reminded_at', 'reason'])]
class SpeakerConfirmation extends Model
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
        return [
            'status' => ConfirmationStatus::class,
            'deadline_at' => 'datetime',
            'responded_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }
}
