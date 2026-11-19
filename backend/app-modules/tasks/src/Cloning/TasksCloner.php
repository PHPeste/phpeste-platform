<?php

declare(strict_types=1);

namespace PHPeste\Tasks\Cloning;

use PHPeste\Community\Contracts\EditionCloner;
use PHPeste\Tasks\Models\Task;
use PHPeste\Tasks\Models\TaskColumn;

/**
 * Leva as colunas e as tarefas para a próxima edição, todas na primeira
 * coluna e sem responsável, prazo nem vínculo: vira o checklist de
 * organização do ano seguinte.
 */
class TasksCloner implements EditionCloner
{
    public function key(): string
    {
        return 'tasks';
    }

    public function label(): string
    {
        return 'Quadro de tarefas (como checklist, sem responsáveis nem prazos)';
    }

    public function clone(string $sourceEditionId, string $targetEditionId): void
    {
        $columns = TaskColumn::query()->forEdition($sourceEditionId)->orderBy('position')->get();
        $first = null;

        foreach ($columns as $column) {
            $copy = TaskColumn::create(['edition_id' => $targetEditionId, 'name' => $column->name, 'position' => $column->position, 'is_done' => $column->is_done]);
            $first ??= $copy->is_done ? null : $copy;
        }

        if ($first === null) {
            return;
        }

        $tasks = Task::query()->forEdition($sourceEditionId)->join('task_columns', 'task_columns.id', '=', 'tasks.task_column_id')
            ->orderBy('task_columns.position')->orderBy('tasks.position')->get(['tasks.title', 'tasks.description']);

        foreach ($tasks->values() as $position => $task) {
            Task::create(['edition_id' => $targetEditionId, 'task_column_id' => $first->id, 'position' => $position, 'title' => $task->title, 'description' => $task->description]);
        }
    }
}
