<?php

declare(strict_types=1);

namespace PHPeste\Tasks\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * Coluna do quadro. `is_done`: tarefa que entra nela conta como concluída
 * (não fica atrasada nem recebe lembrete).
 *
 * @property string $id
 * @property string $edition_id
 * @property string $name
 * @property int $position
 * @property bool $is_done
 */
#[Fillable(['edition_id', 'name', 'position', 'is_done'])]
#[Table(name: 'task_columns')]
class TaskColumn extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @var list<array{name: string, is_done: bool}>
     */
    public const array DEFAULTS = [
        ['name' => 'A fazer', 'is_done' => false],
        ['name' => 'Fazendo', 'is_done' => false],
        ['name' => 'Aguardando', 'is_done' => false],
        ['name' => 'Feito', 'is_done' => true],
    ];

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer', 'is_done' => 'boolean'];
    }
}
