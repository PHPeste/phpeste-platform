<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $name
 * @property int $slots
 * @property int $position
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['slots' => 'integer', 'position' => 'integer'];
    }
}
