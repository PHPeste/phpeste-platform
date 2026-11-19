<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Tasks\Actions\MoveTask;
use PHPeste\Tasks\Models\Task;

require __DIR__.'/../../../../vendor/autoload.php';

$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// SQLite: IMMEDIATE faz a transação pegar o lock de escrita no começo,
// como o lockForUpdate faz no MySQL.
config(['database.connections.sqlite.busy_timeout' => 15_000, 'database.connections.sqlite.transaction_mode' => 'IMMEDIATE']);

[, $editionId, $taskId, $columnId, $position] = $argv;

resolve(CurrentEdition::class)->run($editionId, function () use ($taskId, $columnId, $position): void {
    resolve(MoveTask::class)->handle(Task::query()->findOrFail($taskId), $columnId, (int) $position);
});

fwrite(STDOUT, 'ok');
