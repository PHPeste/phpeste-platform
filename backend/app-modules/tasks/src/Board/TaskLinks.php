<?php

declare(strict_types=1);

namespace PHPeste\Tasks\Board;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Validation\ValidationException;
use PHPeste\Tasks\Contracts\TaskLinkProvider;
use PHPeste\Tasks\Models\Task;

/**
 * Junta os `TaskLinkProvider` registrados pelos outros módulos.
 */
final class TaskLinks
{
    /**
     * @var array<string, TaskLinkProvider>|null
     */
    private ?array $providers = null;

    public function __construct(private readonly Application $app) {}

    /**
     * @return array<string, TaskLinkProvider>
     */
    public function providers(): array
    {
        if ($this->providers === null) {
            $this->providers = [];

            foreach ($this->app->tagged(TaskLinkProvider::TAG) as $provider) {
                if ($provider instanceof TaskLinkProvider) {
                    $this->providers[$provider->key()] = $provider;
                }
            }
        }

        return $this->providers;
    }

    public function ensure(string $editionId, ?string $type, ?string $id): void
    {
        if ($type === null && $id === null) {
            return;
        }

        $provider = $type === null ? null : ($this->providers()[$type] ?? null);

        if (!$provider instanceof TaskLinkProvider || $id === null) {
            throw ValidationException::withMessages(['link_type' => 'Escolha a que a tarefa se liga: '.implode(', ', array_map(fn (TaskLinkProvider $p): string => mb_strtolower($p->label()), $this->providers())).'.']);
        }

        if ($provider->names($editionId, [$id]) === []) {
            throw ValidationException::withMessages(['link_id' => sprintf('Este %s não é desta edição.', mb_strtolower($provider->label()))]);
        }
    }

    /**
     * @param  iterable<Task>  $tasks
     * @return array<string, array{type: string, type_label: string, id: string, name: string}> por id da tarefa
     */
    public function describe(string $editionId, iterable $tasks): array
    {
        $byType = [];

        foreach ($tasks as $task) {
            if ($task->link_type !== null && $task->link_id !== null) {
                $byType[$task->link_type][] = $task;
            }
        }

        $out = [];

        foreach ($byType as $type => $group) {
            $provider = $this->providers()[$type] ?? null;

            if (!$provider instanceof TaskLinkProvider) {
                continue;
            }

            $names = $provider->names($editionId, array_values(array_unique(array_map(fn (Task $task): string => (string) $task->link_id, $group))));

            foreach ($group as $task) {
                if (isset($names[(string) $task->link_id])) {
                    $out[$task->id] = ['type' => $type, 'type_label' => $provider->label(), 'id' => (string) $task->link_id, 'name' => $names[(string) $task->link_id]];
                }
            }
        }

        return $out;
    }
}
