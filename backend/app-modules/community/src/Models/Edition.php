<?php

declare(strict_types=1);

namespace PHPeste\Community\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use PHPeste\Community\Database\Factories\EditionFactory;
use PHPeste\Community\Enums\BrazilianState;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Enums\SiteMode;

/**
 * @property string $id
 * @property string $community_id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property string|null $city
 * @property BrazilianState|null $state
 * @property string $timezone
 * @property SiteMode $site_mode
 * @property string|null $external_site_url
 * @property string|null $privacy_contact_email
 * @property string|null $privacy_policy_url
 * @property bool $anonymize_after_event
 * @property EditionStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Community $community
 */
#[Fillable(['community_id', 'name', 'slug', 'starts_on', 'ends_on', 'city', 'state', 'timezone', 'status', 'site_mode', 'external_site_url', 'privacy_contact_email', 'privacy_policy_url', 'anonymize_after_event'])]
class Edition extends Model
{
    /** @use HasFactory<EditionFactory> */
    use HasFactory;

    use HasUuids;

    protected $attributes = [
        'status' => 'draft',
        'timezone' => 'America/Fortaleza',
        'site_mode' => 'platform',
        'anonymize_after_event' => false,
    ];

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * @return HasMany<EditionMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(EditionMember::class);
    }

    public function roleOf(string $userId): ?EditionRole
    {
        return $this->members()->where('user_id', $userId)->first()?->role;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'state' => BrazilianState::class,
            'status' => EditionStatus::class,
            'site_mode' => SiteMode::class,
            'anonymize_after_event' => 'boolean',
        ];
    }
}
