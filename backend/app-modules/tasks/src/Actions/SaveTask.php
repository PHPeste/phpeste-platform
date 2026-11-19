<?php

declare(strict_types=1);

namespace PHPeste\Tasks\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Community\Contracts\EditionMemberships;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Notifications\Enums\EmailTemplate;
use PHPeste\Tasks\Board\TaskLinks;
use PHPeste\Tasks\Models\Task;
use PHPeste\Tasks\Models\TaskColumn;

/**
 * Cria a tarefa no fim da coluna ou edita os dados dela. Mudar de coluna e
 * de posição é com `MoveTask`. Trocar o prazo libera um novo lembrete;
 * virar responsável manda e-mail (a não ser que a própria pessoa tenha se
 * atribuído).
 */
class SaveTask
{
    public function __construct(
        private readonly EditionMemberships $memberships,
        private readonly TaskLinks $links,
        private readonly TaskNotifier $notifier,
        private readonly Columns $columns,
    ) {}

    /**
     * @param  array{title?: string, description?: string|null, assignee_id?: string|null, due_on?: Carbon|null, link_type?: string|null, link_id?: string|null, task_column_id?: string}  $attributes
     */
    public function handle(string $editionId, ?Task $task, array $attributes, ?string $actorId): Task
    {
        if (($attributes['assignee_id'] ?? null) !== null && !$this->memberships->roleIn($editionId, (string) $attributes['assignee_id']) instanceof EditionRole) {
            throw ValidationException::withMessages(['assignee_id' => 'O responsável precisa ser da equipe desta edição.']);
        }

        if (array_key_exists('link_type', $attributes) || array_key_exists('link_id', $attributes)) {
            $this->links->ensure($editionId, $attributes['link_type'] ?? null, $attributes['link_id'] ?? null);
        }

        $previousAssignee = $task?->assignee_id;

        /** @var Task $saved */
        $saved = DB::transaction(function () use ($editionId, $task, $attributes, $actorId): Task {
            if (!$task instanceof Task) {
                $columns = $this->columns->forEdition($editionId);
                $columnId = $attributes['task_column_id'] ?? $columns->first()?->id;
                $column = TaskColumn::query()->forEdition($editionId)->whereKey($columnId)->lockForUpdate()->first();

                if (!$column instanceof TaskColumn) {
                    throw ValidationException::withMessages(['task_column_id' => 'Esta coluna não é desta edição.']);
                }

                return Task::create([
                    ...$attributes,
                    'edition_id' => $editionId,
                    'task_column_id' => $column->id,
                    'position' => Task::query()->forEdition($editionId)->where('task_column_id', $column->id)->count(),
                    'completed_at' => $column->is_done ? now() : null,
                    'created_by' => $actorId,
                ]);
            }

            unset($attributes['task_column_id']);

            if (array_key_exists('due_on', $attributes) && $attributes['due_on']?->toDateString() !== $task->due_on?->toDateString()) {
                $attributes['due_reminded_at'] = null;
            }

            $task->update($attributes);

            return $task;
        });

        if (!in_array($saved->assignee_id, [null, $previousAssignee, $actorId], true)) {
            $this->notifier->send($saved, EmailTemplate::TaskAssigned);
        }

        return $saved;
    }
}
