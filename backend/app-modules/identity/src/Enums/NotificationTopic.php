<?php

declare(strict_types=1);

namespace PHPeste\Identity\Enums;

enum NotificationTopic: string
{
    case ProposalsToReview = 'proposals_to_review';
    case PaymentFailures = 'payment_failures';
    case AssignedTasks = 'assigned_tasks';
    case WeeklySummary = 'weekly_summary';

    public function label(): string
    {
        return match ($this) {
            self::ProposalsToReview => 'Novas propostas para avaliar',
            self::PaymentFailures => 'Pagamentos recusados',
            self::AssignedTasks => 'Tarefas atribuídas a mim',
            self::WeeklySummary => 'Resumo semanal da edição',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ProposalsToReview => 'Quando a organização atribui propostas para você revisar.',
            self::PaymentFailures => 'Quando um pagamento de ingresso é recusado pelo gateway.',
            self::AssignedTasks => 'Quando alguém da equipe atribui uma tarefa a você.',
            self::WeeklySummary => 'Toda segunda, com vendas, propostas e pendências das suas edições.',
        };
    }

    public function enabledByDefault(): bool
    {
        return $this !== self::WeeklySummary;
    }
}
