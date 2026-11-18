<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $name
 * @property string|null $description
 * @property int $weight
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['edition_id', 'name', 'description', 'weight', 'position'])]
#[Table(name: 'cfp_criteria')]
class CfpCriterion extends Model
{
    use BelongsToEdition;
    use HasUuids;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['weight' => 'integer', 'position' => 'integer'];
    }
}
