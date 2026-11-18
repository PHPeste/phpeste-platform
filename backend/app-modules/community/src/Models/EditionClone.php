<?php

declare(strict_types=1);

namespace PHPeste\Community\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $source_edition_id
 * @property string|null $target_edition_id
 * @property string $requested_by
 * @property string $name
 * @property string $slug
 * @property list<string> $parts
 * @property string $status
 * @property string|null $error
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Edition $source
 */
#[Fillable(['source_edition_id', 'target_edition_id', 'requested_by', 'name', 'slug', 'parts', 'status', 'error', 'finished_at'])]
class EditionClone extends Model
{
    use HasUuids;

    public const string QUEUED = 'queued';

    public const string DONE = 'done';

    public const string FAILED = 'failed';

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Edition::class, 'source_edition_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parts' => 'array',
            'finished_at' => 'datetime',
        ];
    }
}
