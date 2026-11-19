<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PHPeste\Community\Models\Edition;
use PHPeste\Tasks\Models\Task;
use PHPeste\Tasks\Models\TaskColumn;

it('reordenar cards em paralelo não duplica nem perde posição', function (int $seed): void {
    mt_srand($seed);
    $file = tempnam(sys_get_temp_dir(), 'tasks').'.sqlite';
    touch($file);
    config(['database.connections.sqlite.database' => $file]);
    DB::purge('sqlite');
    Artisan::call('migrate', ['--force' => true]);

    $edition = Edition::factory()->create();
    $columns = collect(['A fazer', 'Fazendo', 'Feito'])->map(fn (string $name, int $i): TaskColumn => TaskColumn::create(['edition_id' => $edition->id, 'name' => $name, 'position' => $i, 'is_done' => $i === 2]));
    $tasks = collect(range(0, 14))->map(fn (int $i): Task => Task::create(['edition_id' => $edition->id, 'task_column_id' => $columns[$i % 2]->id, 'position' => intdiv($i, 2), 'title' => "Tarefa {$i}"]));

    $moves = $tasks->shuffle()->take(12)->map(fn (Task $task): array => [$task->id, $columns[mt_rand(0, 2)]->id, mt_rand(0, 8)])->values();
    $script = __DIR__.'/../Fixtures/move.php';

    $results = Process::pool(function ($pool) use ($script, $edition, $file, $moves): void {
        foreach ($moves as $index => [$taskId, $columnId, $position]) {
            $pool->as((string) $index)->env(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $file, 'APP_ENV' => 'testing'])->command([PHP_BINARY, $script, $edition->id, $taskId, $columnId, (string) $position]);
        }
    })->start()->wait();

    expect(collect($results)->filter(fn ($result): bool => mb_trim($result->output()) === 'ok')->count())->toBe($moves->count());

    $after = Task::query()->forEdition($edition->id)->get();

    expect($after)->toHaveCount(15)
        ->and($after->pluck('id')->sort()->values()->all())->toBe($tasks->pluck('id')->sort()->values()->all());

    foreach ($columns as $column) {
        $positions = $after->where('task_column_id', $column->id)->pluck('position')->sort()->values()->all();

        expect($positions)->toBe($positions === [] ? [] : range(0, count($positions) - 1), "coluna {$column->name}, semente {$seed}");
    }

    foreach ($moves as [$taskId, $columnId]) {
        // Cada tarefa terminou na coluna do último movimento que a levou.
        $last = $moves->filter(fn (array $move): bool => $move[0] === $taskId)->last();
        expect($after->firstWhere('id', $taskId)->task_column_id)->toBe($last[1]);
    }

    expect($after->where('task_column_id', $columns[2]->id)->every(fn (Task $task): bool => $task->completed_at !== null))->toBeTrue();

    File::delete($file);
})->with([1, 2, 3]);
