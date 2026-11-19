<?php

declare(strict_types=1);

namespace PHPeste\Tasks\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Notifications\Enums\EmailTemplate;
use PHPeste\Tasks\Actions\TaskNotifier;
use PHPeste\Tasks\Models\Task;

/**
 * Avisa o responsável da tarefa que vence amanhã (ou hoje, se o lembrete
 * ainda não saiu). Um lembrete por prazo: mudar o prazo libera outro.
 */
#[Signature('tasks:due-reminders')]
#[Description('Avisa por e-mail o responsável das tarefas que vencem até amanhã')]
class SendDueReminders extends Command
{
    public function handle(CurrentEdition $currentEdition, TaskNotifier $notifier): int
    {
        $due = Task::query()->withoutGlobalScopes()
            ->whereNull('completed_at')
            ->whereNull('due_reminded_at')
            ->whereNotNull('assignee_id')
            ->whereDate('due_on', '>=', today()->toDateString())
            ->whereDate('due_on', '<=', today()->addDay()->toDateString())
            ->get(['id', 'edition_id']);

        $sent = 0;

        foreach ($due as $row) {
            $currentEdition->run($row->edition_id, function () use ($row, $notifier, &$sent): void {
                $task = Task::query()->find($row->id);

                if (!$task instanceof Task) {
                    return;
                }

                $updated = Task::query()->whereKey($task->id)->whereNull('due_reminded_at')->update(['due_reminded_at' => now()]);

                if ($updated > 0 && $notifier->send($task, EmailTemplate::TaskDueSoon)) {
                    $sent++;
                }
            });
        }

        $this->info(sprintf('%d lembrete(s) de prazo enviado(s).', $sent));

        return self::SUCCESS;
    }
}
