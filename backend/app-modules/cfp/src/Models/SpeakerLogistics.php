<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Enums\BrazilianState;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $user_id
 * @property string|null $origin_city
 * @property BrazilianState|null $origin_state
 * @property bool $needs_travel
 * @property bool $needs_lodging
 * @property bool $travel_paid_by_organization
 * @property bool $lodging_paid_by_organization
 * @property string|null $organization_notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'user_id', 'origin_city', 'origin_state', 'needs_travel', 'needs_lodging', 'travel_paid_by_organization', 'lodging_paid_by_organization', 'organization_notes'])]
#[Table(name: 'speaker_logistics')]
class SpeakerLogistics extends Model
{
    use BelongsToEdition;
    use HasUuids;

    protected $attributes = [
        'needs_travel' => false,
        'needs_lodging' => false,
        'travel_paid_by_organization' => false,
        'lodging_paid_by_organization' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin_state' => BrazilianState::class,
            'needs_travel' => 'boolean',
            'needs_lodging' => 'boolean',
            'travel_paid_by_organization' => 'boolean',
            'lodging_paid_by_organization' => 'boolean',
        ];
    }
}
