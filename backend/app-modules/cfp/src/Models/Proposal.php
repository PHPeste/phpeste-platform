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
use PHPeste\Cfp\Enums\ProposalFormat;
use PHPeste\Cfp\Enums\ProposalLanguage;
use PHPeste\Cfp\Enums\ProposalLevel;
use PHPeste\Cfp\Enums\ProposalStatus;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * @property string $id
 * @property string $edition_id
 * @property int $number
 * @property string|null $track_id
 * @property string $title
 * @property string|null $summary
 * @property string|null $audience
 * @property ProposalFormat|null $format
 * @property ProposalLevel|null $level
 * @property ProposalLanguage $language
 * @property ProposalStatus $status
 * @property Carbon|null $submitted_at
 * @property Carbon|null $withdrawn_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read CfpTrack|null $track
 * @property-read Collection<int, ProposalSpeaker> $speakers
 */
#[Fillable(['edition_id', 'number', 'track_id', 'title', 'summary', 'audience', 'format', 'level', 'language', 'status', 'submitted_at', 'withdrawn_at'])]
class Proposal extends Model
{
    use BelongsToEdition;
    use HasUuids;

    protected $attributes = [
        'status' => 'draft',
        'language' => 'pt_BR',
    ];

    /**
     * @return BelongsTo<CfpTrack, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(CfpTrack::class);
    }

    /**
     * @return HasMany<ProposalSpeaker, $this>
     */
    public function speakers(): HasMany
    {
        return $this->hasMany(ProposalSpeaker::class);
    }

    public function ownerId(): ?string
    {
        return $this->speakers->firstWhere('is_owner', true)?->user_id;
    }

    public function hasSpeaker(string $userId): bool
    {
        return $this->speakers->contains('user_id', $userId);
    }

    /**
     * @return list<string>
     */
    public function missingForSubmission(): array
    {
        return array_keys(array_filter([
            'track_id' => $this->track_id === null,
            'summary' => $this->summary === null || mb_trim($this->summary) === '',
            'audience' => $this->audience === null || mb_trim($this->audience) === '',
            'format' => $this->format === null,
            'level' => $this->level === null,
        ]));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'format' => ProposalFormat::class,
            'level' => ProposalLevel::class,
            'language' => ProposalLanguage::class,
            'status' => ProposalStatus::class,
            'submitted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }
}
