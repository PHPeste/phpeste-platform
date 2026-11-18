<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Ticketing\Enums\TicketVisibility;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $name
 * @property string|null $description
 * @property TicketVisibility $visibility
 * @property string|null $access_code
 * @property int $min_per_order
 * @property int $max_per_order
 * @property bool $show_remaining
 * @property list<array{id: string, label: string, type: string, required: bool, options: list<string>}>|null $questions
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, TicketBatch> $batches
 */
#[Fillable(['edition_id', 'name', 'description', 'visibility', 'access_code', 'min_per_order', 'max_per_order', 'show_remaining', 'questions', 'position'])]
#[Table(name: 'ticket_types')]
class TicketType extends Model
{
    use BelongsToEdition;
    use HasUuids;

    protected $attributes = ['visibility' => 'public', 'min_per_order' => 1, 'max_per_order' => 10, 'show_remaining' => false, 'position' => 0];

    /**
     * @return HasMany<TicketBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(TicketBatch::class)->orderBy('position');
    }

    public function currentBatch(?Carbon $at = null): ?TicketBatch
    {
        $at ??= now();

        return $this->batches->first(fn (TicketBatch $batch): bool => $batch->isOpen($at));
    }

    public function allocated(): int
    {
        return (int) $this->batches->sum('allocated');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => TicketVisibility::class,
            'min_per_order' => 'integer',
            'max_per_order' => 'integer',
            'show_remaining' => 'boolean',
            'questions' => 'array',
            'position' => 'integer',
        ];
    }
}
