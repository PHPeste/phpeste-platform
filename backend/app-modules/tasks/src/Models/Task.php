<?php

declare(strict_types=1);

namespace PHPeste\Tasks\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $task_column_id
 * @property int $position
 * @property string $title
 * @property string|null $description
 * @property string|null $assignee_id
 * @property Carbon|null $due_on
 * @property string|null $link_type
 * @property string|null $link_id
 * @property Carbon|null $completed_at
 * @property Carbon|null $due_reminded_at
 * @property string|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read TaskColumn $column
 */
#[Fillable(['edition_id', 'task_column_id', 'position', 'title', 'description', 'assignee_id', 'due_on', 'link_type', 'link_id', 'completed_at', 'due_reminded_at', 'created_by'])]
#[Table(name: 'tasks')]
class Task extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return BelongsTo<TaskColumn, $this>
     */
    public function column(): BelongsTo
    {
        return $this->belongsTo(TaskColumn::class, 'task_column_id');
    }

    /**
     * Atrasada: prazo antes de hoje e fora da coluna de concluídas.
     */
    public function overdue(): bool
    {
        return $this->completed_at === null && $this->due_on !== null && $this->due_on->lt(today());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'due_on' => 'date',
            'completed_at' => 'datetime',
            'due_reminded_at' => 'datetime',
        ];
    }
}
