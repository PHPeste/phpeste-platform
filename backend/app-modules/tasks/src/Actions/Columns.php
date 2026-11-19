<?php

declare(strict_types=1);

namespace PHPeste\Tasks\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Tasks\Models\Task;
use PHPeste\Tasks\Models\TaskColumn;

/**
 * Colunas do quadro: as quatro padrão nascem na primeira leitura e a
 * organização renomeia, cria, reordena e remove (só vazias). Sempre fica
 * pelo menos uma coluna de concluídas.
 */
class Columns
{
    /**
     * @return Collection<int, TaskColumn>
     */
    public function forEdition(string $editionId): Collection
    {
        if (!TaskColumn::query()->forEdition($editionId)->exists()) {
            DB::transaction(function () use ($editionId): void {
                if (TaskColumn::query()->forEdition($editionId)->lockForUpdate()->exists()) {
                    return;
                }

                foreach (TaskColumn::DEFAULTS as $position => $column) {
                    TaskColumn::create(['edition_id' => $editionId, 'position' => $position, ...$column]);
                }
            });
        }

        return TaskColumn::query()->forEdition($editionId)->orderBy('position')->orderBy('id')->get();
    }

    /**
     * @param  array{name?: string, is_done?: bool}  $attributes
     */
    public function save(string $editionId, ?TaskColumn $column, array $attributes): TaskColumn
    {
        $this->forEdition($editionId);

        if ($column instanceof TaskColumn && ($attributes['is_done'] ?? true) === false && $column->is_done && $this->doneCount($editionId) === 1) {
            throw ValidationException::withMessages(['is_done' => 'O quadro precisa de pelo menos uma coluna de concluídas.']);
        }

        /** @var TaskColumn */
        return DB::transaction(function () use ($editionId, $column, $attributes): TaskColumn {
            if (!$column instanceof TaskColumn) {
                return TaskColumn::create([
                    'edition_id' => $editionId,
                    'position' => (int) TaskColumn::query()->forEdition($editionId)->max('position') + 1,
                    'is_done' => false,
                    ...$attributes,
                ]);
            }

            $column->update($attributes);

            if (array_key_exists('is_done', $attributes)) {
                Task::query()->forEdition($editionId)->where('task_column_id', $column->id)
                    ->update(['completed_at' => $column->is_done ? now() : null, 'updated_at' => now()]);
            }

            return $column;
        });
    }

    public function delete(TaskColumn $column): void
    {
        if (Task::query()->forEdition($column->edition_id)->where('task_column_id', $column->id)->exists()) {
            throw ValidationException::withMessages(['column' => 'A coluna tem tarefas. Mova as tarefas antes de remover.']);
        }

        if ($column->is_done && $this->doneCount($column->edition_id) === 1) {
            throw ValidationException::withMessages(['column' => 'O quadro precisa de pelo menos uma coluna de concluídas.']);
        }

        DB::transaction(function () use ($column): void {
            $column->delete();
            $this->resequence($column->edition_id, TaskColumn::query()->forEdition($column->edition_id)->orderBy('position')->orderBy('id')->pluck('id')->all());
        });
    }

    /**
     * @param  list<string>  $ids  todas as colunas, na ordem nova
     */
    public function reorder(string $editionId, array $ids): void
    {
        DB::transaction(function () use ($editionId, $ids): void {
            $current = TaskColumn::query()->forEdition($editionId)->lockForUpdate()->pluck('id')->all();
            sort($current);
            $given = $ids;
            sort($given);

            if ($current !== $given) {
                throw ValidationException::withMessages(['ids' => 'Mande todas as colunas do quadro, cada uma uma vez.']);
            }

            $this->resequence($editionId, $ids);
        });
    }

    /**
     * @param  array<mixed>  $ids  vindos de um `pluck('id')`, já na ordem nova
     */
    private function resequence(string $editionId, array $ids): void
    {
        $ids = array_values(array_filter($ids, is_string(...)));

        foreach ($ids as $position => $id) {
            TaskColumn::query()->forEdition($editionId)->whereKey($id)->update(['position' => $position]);
        }
    }

    private function doneCount(string $editionId): int
    {
        return TaskColumn::query()->forEdition($editionId)->where('is_done', true)->count();
    }
}
