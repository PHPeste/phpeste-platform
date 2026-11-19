<?php

declare(strict_types=1);

namespace PHPeste\Tasks\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Tasks\Actions\Columns;
use PHPeste\Tasks\Actions\MoveTask;
use PHPeste\Tasks\Actions\SaveTask;
use PHPeste\Tasks\Board\TaskLinks;
use PHPeste\Tasks\Board\TaskPresenter;
use PHPeste\Tasks\Contracts\TaskLinkProvider;
use PHPeste\Tasks\Http\Requests\MoveTaskRequest;
use PHPeste\Tasks\Http\Requests\ReorderColumnsRequest;
use PHPeste\Tasks\Http\Requests\SaveColumnRequest;
use PHPeste\Tasks\Http\Requests\SaveTaskRequest;
use PHPeste\Tasks\Models\Task;
use PHPeste\Tasks\Models\TaskColumn;

/**
 * Quadro de tarefas da organização. Toda a equipe vê o quadro; quem tem
 * `tasks.manage` cria, edita e move qualquer tarefa; o responsável move a
 * própria tarefa.
 */
class TaskBoardController
{
    /**
     * `filter=mine|overdue` (padrão: todas). As contagens das colunas
     * seguem o filtro.
     */
    public function board(Request $request, CurrentEdition $edition, Columns $columns, TaskPresenter $presenter): JsonResponse
    {
        $editionId = (string) $edition->id();
        $filter = (string) $request->query('filter', 'all');
        $me = $this->actor($request);

        $cols = $columns->forEdition($editionId);
        $tasks = Task::query()
            ->when($filter === 'mine', fn ($query) => $query->where('assignee_id', $me))
            ->when($filter === 'overdue', fn ($query) => $query->whereNull('completed_at')->whereDate('due_on', '<', today()->toDateString()))
            ->orderBy('position')->orderBy('id')
            ->get();
        $presenter->load($editionId, $tasks);

        return response()->json(['data' => [
            'filter' => in_array($filter, ['mine', 'overdue'], true) ? $filter : 'all',
            'can_manage' => Gate::allows(EditionPermission::ManageTasks->value),
            'columns' => $cols->map(fn (TaskColumn $column): array => [
                'id' => $column->id,
                'name' => $column->name,
                'position' => $column->position,
                'is_done' => $column->is_done,
                'count' => $tasks->where('task_column_id', $column->id)->count(),
                'tasks' => $tasks->where('task_column_id', $column->id)->values()->map(fn (Task $task): array => $presenter->task($task))->all(),
            ])->all(),
            'totals' => [
                'tasks' => $tasks->count(),
                'overdue' => $tasks->filter(fn (Task $task): bool => $task->overdue())->count(),
            ],
        ]]);
    }

    /**
     * O que dá para ligar a uma tarefa, por tipo (fornecedores, patrocinadores...).
     */
    public function linkOptions(CurrentEdition $edition, TaskLinks $links): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageTasks->value);

        $editionId = (string) $edition->id();

        return response()->json(['data' => array_values(array_map(fn (TaskLinkProvider $provider): array => [
            'type' => $provider->key(),
            'label' => $provider->label(),
            'options' => $provider->options($editionId),
        ], $links->providers()))]);
    }

    public function store(SaveTaskRequest $request, CurrentEdition $edition, SaveTask $save, TaskPresenter $presenter): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageTasks->value);

        $task = $save->handle((string) $edition->id(), null, $request->task(), $this->actor($request));

        return response()->json(['data' => $presenter->load($task->edition_id, [$task])->task($task)], 201);
    }

    public function update(SaveTaskRequest $request, CurrentEdition $edition, SaveTask $save, TaskPresenter $presenter): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageTasks->value);

        $task = $save->handle((string) $edition->id(), $this->find($request, 'task', Task::class), $request->task(), $this->actor($request));

        return response()->json(['data' => $presenter->load($task->edition_id, [$task])->task($task)]);
    }

    /**
     * Devolve a coluna de destino (e a de origem) já renumeradas, para o
     * painel confirmar o movimento otimista ou desfazer.
     */
    public function move(MoveTaskRequest $request, MoveTask $move, TaskPresenter $presenter): JsonResponse
    {
        $task = $this->find($request, 'task', Task::class);

        abort_if(!Gate::allows(EditionPermission::ManageTasks->value) && $task->assignee_id !== $this->actor($request), 403, 'Só quem organiza ou o responsável pela tarefa pode mover.');

        $from = $task->task_column_id;
        $moved = $move->handle($task, $request->string('task_column_id')->toString(), $request->integer('position'));
        $affected = Task::query()->whereIn('task_column_id', array_unique([$from, $moved->task_column_id]))->orderBy('position')->orderBy('id')->get();
        $presenter->load($moved->edition_id, $affected);

        return response()->json(['data' => [
            'task' => $presenter->task($moved),
            'columns' => $affected->groupBy('task_column_id')->map(fn ($group, $columnId): array => [
                'id' => $columnId,
                'task_ids' => $group->pluck('id')->values()->all(),
            ])->values()->all(),
        ]]);
    }

    public function destroy(Request $request, MoveTask $move): Response
    {
        Gate::authorize(EditionPermission::ManageTasks->value);

        $move->delete($this->find($request, 'task', Task::class));

        return response()->noContent();
    }

    public function storeColumn(SaveColumnRequest $request, CurrentEdition $edition, Columns $columns): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageTasks->value);

        return response()->json(['data' => $this->column($columns->save((string) $edition->id(), null, $request->column()))], 201);
    }

    public function updateColumn(SaveColumnRequest $request, CurrentEdition $edition, Columns $columns): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageTasks->value);

        return response()->json(['data' => $this->column($columns->save((string) $edition->id(), $this->find($request, 'column', TaskColumn::class), $request->column()))]);
    }

    public function destroyColumn(Request $request, Columns $columns): Response
    {
        Gate::authorize(EditionPermission::ManageTasks->value);

        $columns->delete($this->find($request, 'column', TaskColumn::class));

        return response()->noContent();
    }

    public function reorderColumns(ReorderColumnsRequest $request, CurrentEdition $edition, Columns $columns): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageTasks->value);

        $editionId = (string) $edition->id();
        $columns->reorder($editionId, $request->ids());

        return response()->json(['data' => $columns->forEdition($editionId)->map(fn (TaskColumn $column): array => $this->column($column))->all()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function column(TaskColumn $column): array
    {
        return ['id' => $column->id, 'name' => $column->name, 'position' => $column->position, 'is_done' => $column->is_done];
    }

    private function actor(Request $request): ?string
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_string($id) ? $id : null;
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return TModel
     */
    private function find(Request $request, string $parameter, string $model): Model
    {
        $id = $request->route($parameter);
        abort_unless(is_string($id), 404);

        return $model::query()->whereKey($id)->firstOrFail();
    }
}
