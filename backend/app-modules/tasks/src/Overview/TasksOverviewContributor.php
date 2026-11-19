<?php

declare(strict_types=1);

namespace PHPeste\Tasks\Overview;

use PHPeste\Community\Contracts\EditionOverviewContributor;
use PHPeste\Community\Data\OverviewContribution;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Tasks\Models\Task;

class TasksOverviewContributor implements EditionOverviewContributor
{
    public function contribute(string $editionId): OverviewContribution
    {
        $overdue = Task::query()->forEdition($editionId)->whereNull('completed_at')->whereDate('due_on', '<', today()->toDateString())->count();

        return new OverviewContribution(pending: $overdue === 0 ? [] : [[
            'key' => 'tasks_overdue',
            'title' => sprintf('%d %s atrasada%s', $overdue, $overdue === 1 ? 'tarefa' : 'tarefas', $overdue === 1 ? '' : 's'),
            'tone' => 'info',
            'tag' => 'Organização',
            'action_label' => 'Abrir quadro',
            'action_path' => '/tarefas?filtro=atrasadas',
            'priority' => 3,
            'permission' => EditionPermission::ManageTasks,
        ]]);
    }
}
