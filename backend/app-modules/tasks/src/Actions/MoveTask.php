<?php

declare(strict_types=1);

namespace PHPeste\Tasks\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Tasks\Models\Task;
use PHPeste\Tasks\Models\TaskColumn;

/**
 * Move a tarefa para uma coluna e posição (arrastar o card ou teclado).
 *
 * Concorrência: as colunas de origem e destino são travadas, sempre na
 * mesma ordem (pelo id), e as duas são renumeradas de 0 a n−1 dentro da
 * mesma transação. Dois movimentos ao mesmo tempo esperam um pelo outro,
 * e a numeração nunca fica com buraco nem posição repetida.
 */
class MoveTask
{
    public function handle(Task $task, string $columnId, int $position): Task
    {
        $editionId = $task->edition_id;

        for ($attempt = 1; ; $attempt++) {
            $moved = DB::transaction(fn (): bool => $this->move($task, $editionId, $columnId, $position));

            if ($moved) {
                return $task->refresh();
            }

            // Outra transação mudou a tarefa de coluna enquanto travávamos as
            // colunas: tenta de novo com a coluna atual.
            if ($attempt === 3) {
                throw ValidationException::withMessages(['task' => 'A tarefa mudou enquanto isso. Atualize o quadro e tente de novo.']);
            }
        }
    }

    /**
     * Remove a tarefa e fecha o buraco na coluna.
     */
    public function delete(Task $task): void
    {
        DB::transaction(function () use ($task): void {
            TaskColumn::query()->forEdition($task->edition_id)->whereKey($task->task_column_id)->lockForUpdate()->first();
            $task->delete();
            $this->resequence($task->edition_id, Task::query()->forEdition($task->edition_id)->where('task_column_id', $task->task_column_id)
                ->orderBy('position')->orderBy('id')->pluck('id')->all());
        });
    }

    private function move(Task $task, string $editionId, string $columnId, int $position): bool
    {
        $from = Task::query()->forEdition($editionId)->whereKey($task->id)->value('task_column_id');

        if (!is_string($from)) {
            throw ValidationException::withMessages(['task' => 'A tarefa foi removida.']);
        }

        $locked = TaskColumn::query()->forEdition($editionId)->whereKey(array_unique([$from, $columnId]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $fresh = Task::query()->forEdition($editionId)->whereKey($task->id)->first();

        if (!$fresh instanceof Task) {
            throw ValidationException::withMessages(['task' => 'A tarefa foi removida.']);
        }

        if ($fresh->task_column_id !== $from) {
            return false;
        }

        $target = $locked->get($columnId);

        if (!$target instanceof TaskColumn) {
            throw ValidationException::withMessages(['task_column_id' => 'Esta coluna não é desta edição.']);
        }

        $others = Task::query()->forEdition($editionId)->where('task_column_id', $columnId)->whereKeyNot($fresh->id)
            ->orderBy('position')->orderBy('id')->pluck('id')->all();
        $index = max(0, min($position, count($others)));
        array_splice($others, $index, 0, [$fresh->id]);

        Task::query()->forEdition($editionId)->whereKey($fresh->id)->update([
            'task_column_id' => $columnId,
            'completed_at' => $target->is_done ? ($fresh->completed_at ?? now()) : null,
            'updated_at' => now(),
        ]);

        $this->resequence($editionId, $others);

        if ($from !== $columnId) {
            $this->resequence($editionId, Task::query()->forEdition($editionId)->where('task_column_id', $from)
                ->orderBy('position')->orderBy('id')->pluck('id')->all());
        }

        return true;
    }

    /**
     * @param  array<mixed>  $ids  vindos de um `pluck('id')`, já na ordem nova
     */
    private function resequence(string $editionId, array $ids): void
    {
        $ids = array_values(array_filter($ids, is_string(...)));

        foreach ($ids as $position => $id) {
            Task::query()->forEdition($editionId)->whereKey($id)->where('position', '!=', $position)->update(['position' => $position]);
        }
    }
}
