<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * @property string $id
 * @property string $edition_id
 * @property int|null $goal_cents
 */
#[Fillable(['edition_id', 'goal_cents'])]
#[Table(name: 'sponsor_settings')]
class SponsorSetting extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['goal_cents' => 'integer'];
    }
}
