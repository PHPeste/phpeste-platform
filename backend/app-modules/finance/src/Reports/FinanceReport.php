<?php

declare(strict_types=1);

namespace PHPeste\Finance\Reports;

use Illuminate\Support\Collection;
use PHPeste\Finance\Actions\Categories;
use PHPeste\Finance\Enums\ExpenseStatus;
use PHPeste\Finance\Models\Expense;
use PHPeste\Finance\Models\FinanceCategory;
use PHPeste\Payments\Contracts\PaymentAccounts;
use PHPeste\Payments\Data\ReceivingAccount;
use PHPeste\Sponsors\Contracts\SponsorRevenue;
use PHPeste\Ticketing\Contracts\TicketRevenue;
use PHPeste\Ticketing\Data\TicketRevenueLine;

/**
 * A visão financeira da edição, em centavos. A receita não é guardada:
 * sai na hora dos pedidos pagos (Ticketing) e dos pagamentos de patrocínio
 * (Sponsors), então nunca diverge da origem.
 *
 * - receita de ingressos = pagos − reembolsos − taxas do gateway, por conta
 *   de recebimento (quem recebe em CPF declara a parte da própria conta);
 * - saldo = receita líquida recebida − despesas pagas;
 * - previsão = saldo + patrocínio a receber − despesas a pagar.
 */
final readonly class FinanceReport
{
    public function __construct(
        private TicketRevenue $tickets,
        private SponsorRevenue $sponsors,
        private PaymentAccounts $accounts,
        private Categories $categories,
    ) {}

    /**
     * @return array{
     *     revenue: array{
     *         tickets: array{gross: int, refunded: int, fees: int, net: int, by_account: list<array{account: array{id: string, driver: string, holder_type: string, holder_name: string, holder_document: string|null, retired: bool}|null, orders: int, gross: int, refunded: int, fees: int, net: int, estimated_fees: int}>},
     *         sponsors: array{received: int, outstanding: int},
     *         total: int,
     *     },
     *     expenses: array{paid: int, pending: int, overdue: int},
     *     balance: int,
     *     forecast: int,
     *     budget: array{categories: list<array{id: string, name: string, budget: int|null, committed: int, paid: int, remaining: int|null, percent_used: int|null, over_budget: bool}>, budgeted: int, committed: int, paid: int},
     * }
     */
    public function summary(string $editionId): array
    {
        $accounts = collect($this->accounts->all($editionId))->keyBy(fn (ReceivingAccount $account): string => $account->id);
        $lines = $this->tickets->byAccount($editionId);
        $sponsors = $this->sponsors->summary($editionId);

        $byAccount = array_map(fn (TicketRevenueLine $line): array => [
            'account' => $this->account($line->paymentAccountId === null ? null : $accounts->get($line->paymentAccountId)),
            'orders' => $line->orders,
            'gross' => $line->grossCents,
            'refunded' => $line->refundedCents,
            'fees' => $line->feeCents,
            'net' => $line->netCents(),
            'estimated_fees' => $line->estimatedFees,
        ], $lines);

        $ticketsNet = array_sum(array_map(fn (TicketRevenueLine $line): int => $line->netCents(), $lines));
        $expenses = Expense::query()->forEdition($editionId)->where('status', '!=', ExpenseStatus::Cancelled->value)->get(['id', 'finance_category_id', 'amount_cents', 'status', 'due_on']);
        $paid = (int) $expenses->where('status', ExpenseStatus::Paid)->sum('amount_cents');
        $pending = (int) $expenses->where('status', ExpenseStatus::Pending)->sum('amount_cents');
        $revenue = $ticketsNet + $sponsors['received'];
        $balance = $revenue - $paid;

        return [
            'revenue' => [
                'tickets' => [
                    'gross' => array_sum(array_column($byAccount, 'gross')),
                    'refunded' => array_sum(array_column($byAccount, 'refunded')),
                    'fees' => array_sum(array_column($byAccount, 'fees')),
                    'net' => $ticketsNet,
                    'by_account' => $byAccount,
                ],
                'sponsors' => ['received' => $sponsors['received'], 'outstanding' => $sponsors['outstanding']],
                'total' => $revenue,
            ],
            'expenses' => [
                'paid' => $paid,
                'pending' => $pending,
                'overdue' => $expenses->filter(fn (Expense $expense): bool => $expense->overdue())->count(),
            ],
            'balance' => $balance,
            'forecast' => $balance + $sponsors['outstanding'] - $pending,
            'budget' => $this->budget($editionId, $expenses),
        ];
    }

    /**
     * @param  Collection<int, Expense>  $expenses
     * @return array{categories: list<array{id: string, name: string, budget: int|null, committed: int, paid: int, remaining: int|null, percent_used: int|null, over_budget: bool}>, budgeted: int, committed: int, paid: int}
     */
    private function budget(string $editionId, Collection $expenses): array
    {
        $rows = $this->categories->forEdition($editionId)->map(function (FinanceCategory $category) use ($expenses): array {
            $mine = $expenses->where('finance_category_id', $category->id);
            $committed = (int) $mine->sum('amount_cents');
            $paid = (int) $mine->where('status', ExpenseStatus::Paid)->sum('amount_cents');

            return [
                'id' => $category->id,
                'name' => $category->name,
                'budget' => $category->budget_cents,
                'committed' => $committed,
                'paid' => $paid,
                'remaining' => $category->budget_cents === null ? null : $category->budget_cents - $committed,
                'percent_used' => $category->budget_cents === null || $category->budget_cents === 0 ? null : (int) floor($committed * 100 / $category->budget_cents),
                'over_budget' => $category->budget_cents !== null && $committed > $category->budget_cents,
            ];
        })->all();
        $rows = array_values($rows);

        return [
            'categories' => $rows,
            'budgeted' => array_sum(array_map(fn (array $row): int => (int) $row['budget'], $rows)),
            'committed' => array_sum(array_column($rows, 'committed')),
            'paid' => array_sum(array_column($rows, 'paid')),
        ];
    }

    /**
     * @return array{id: string, driver: string, holder_type: string, holder_name: string, holder_document: string|null, retired: bool}|null
     */
    private function account(?ReceivingAccount $account): ?array
    {
        if (!$account instanceof ReceivingAccount) {
            return null;
        }

        return [
            'id' => $account->id,
            'driver' => $account->driver,
            'holder_type' => $account->holderType->value,
            'holder_name' => $account->holderName,
            'holder_document' => $account->holderDocumentMasked,
            'retired' => $account->retired,
        ];
    }
}
