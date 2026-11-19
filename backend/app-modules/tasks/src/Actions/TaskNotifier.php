<?php

declare(strict_types=1);

namespace PHPeste\Tasks\Actions;

use PHPeste\Community\Contracts\EditionMemberships;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Identity\Contracts\UserAccounts;
use PHPeste\Identity\Enums\NotificationTopic;
use PHPeste\Notifications\Contracts\EditionMailer;
use PHPeste\Notifications\Enums\EmailTemplate;
use PHPeste\Tasks\Models\Task;

/**
 * E-mail de tarefa: só para quem é o responsável, só se ainda é da equipe
 * da edição e só se a pessoa não desligou "Tarefas atribuídas a mim".
 */
class TaskNotifier
{
    public function __construct(
        private readonly EditionMailer $mailer,
        private readonly UserAccounts $accounts,
        private readonly EditionMemberships $memberships,
    ) {}

    public function send(Task $task, EmailTemplate $template): bool
    {
        $userId = $task->assignee_id;

        if ($userId === null || !$this->memberships->roleIn($task->edition_id, $userId) instanceof EditionRole || !$this->accounts->wantsNotification($userId, NotificationTopic::AssignedTasks)) {
            return false;
        }

        $profile = $this->accounts->profiles([$userId])[$userId] ?? null;

        if ($profile === null) {
            return false;
        }

        $this->mailer->send($task->edition_id, $template, $profile['email'], $profile['name'], [
            'nome' => $profile['name'],
            'tarefa' => $task->title,
            'prazo' => $task->due_on === null ? 'sem prazo' : $this->date($task->due_on->toDateString()),
        ], mb_rtrim((string) config('app.admin_url'), '/').'/tarefas?tarefa='.$task->id);

        return true;
    }

    private function date(string $date): string
    {
        $months = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        [$year, $month, $day] = array_map(intval(...), explode('-', $date));

        return sprintf('%d de %s de %d', $day, $months[$month - 1], $year);
    }
}
