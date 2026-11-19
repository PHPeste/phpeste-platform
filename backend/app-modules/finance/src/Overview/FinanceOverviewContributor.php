<?php

declare(strict_types=1);

namespace PHPeste\Finance\Overview;

use PHPeste\Community\Contracts\EditionOverviewContributor;
use PHPeste\Community\Data\OverviewContribution;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Finance\Enums\ExpenseStatus;
use PHPeste\Finance\Models\Expense;

/**
 * Pendência de despesas vencidas, só para quem vê finanças. Os números de
 * saldo ficam na tela de Finanças, que já exige a permissão.
 */
class FinanceOverviewContributor implements EditionOverviewContributor
{
    public function contribute(string $editionId): OverviewContribution
    {
        $overdue = Expense::query()->forEdition($editionId)
            ->where('status', ExpenseStatus::Pending->value)
            ->whereDate('due_on', '<', today()->toDateString())
            ->count();

        return new OverviewContribution(pending: $overdue === 0 ? [] : [[
            'key' => 'expenses_overdue',
            'title' => sprintf('%d %s vencida%s', $overdue, $overdue === 1 ? 'despesa' : 'despesas', $overdue === 1 ? '' : 's'),
            'tone' => 'warn',
            'tag' => 'Finanças',
            'action_label' => 'Ver despesas',
            'action_path' => '/financas/despesas?vencidas=1',
            'priority' => 2,
            'permission' => EditionPermission::ManageFinance,
        ]]);
    }
}
