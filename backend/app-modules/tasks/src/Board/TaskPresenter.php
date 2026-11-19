<?php

declare(strict_types=1);

namespace PHPeste\Tasks\Board;

use PHPeste\Identity\Contracts\UserAccounts;
use PHPeste\Tasks\Models\Task;

/**
 * Monta o card da tarefa com o nome do responsável (Identity) e o nome do
 * que está ligado a ela (módulo dono do vínculo).
 */
final class TaskPresenter
{
    /**
     * @var array<string, array{id: string, name: string}>
     */
    private array $people = [];

    /**
     * @var array<string, array{type: string, type_label: string, id: string, name: string}>
     */
    private array $links = [];

    public function __construct(
        private readonly UserAccounts $accounts,
        private readonly TaskLinks $taskLinks,
    ) {}

    /**
     * @param  iterable<Task>  $tasks
     */
    public function load(string $editionId, iterable $tasks): self
    {
        $list = is_array($tasks) ? $tasks : iterator_to_array($tasks, false);
        $ids = array_values(array_unique(array_filter(array_map(fn (Task $task): ?string => $task->assignee_id, $list))));

        foreach ($this->accounts->profiles($ids) as $id => $profile) {
            $this->people[$id] = ['id' => $profile['id'], 'name' => $profile['name']];
        }

        $this->links = [...$this->links, ...$this->taskLinks->describe($editionId, $list)];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function task(Task $task): array
    {
        return [
            'id' => $task->id,
            'column_id' => $task->task_column_id,
            'position' => $task->position,
            'title' => $task->title,
            'description' => $task->description,
            'assignee' => $task->assignee_id === null ? null : ($this->people[$task->assignee_id] ?? ['id' => $task->assignee_id, 'name' => null]),
            'due_on' => $task->due_on?->toDateString(),
            'overdue' => $task->overdue(),
            'done' => $task->completed_at !== null,
            'link' => $this->links[$task->id] ?? null,
            'updated_at' => $task->updated_at->clone()->utc()->toIso8601ZuluString(),
        ];
    }
}
