<?php

declare(strict_types=1);

namespace PHPeste\Finance\Export;

use PHPeste\Finance\Models\Expense;
use PHPeste\Finance\Reports\FinanceReport;
use PHPeste\Suppliers\Contracts\EditionSuppliers;

/**
 * As abas da planilha de finanças. Valores em reais (número com duas
 * casas), datas em AAAA-MM-DD. Texto que começa com =, +, - ou @ ganha um
 * apóstrofo para a planilha não executar como fórmula (injeção de CSV).
 */
final readonly class FinanceSpreadsheet
{
    public function __construct(
        private FinanceReport $report,
        private EditionSuppliers $suppliers,
    ) {}

    public static function text(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    /**
     * @return array<string, list<list<string|int|float|null>>>
     */
    public function sheets(string $editionId): array
    {
        $summary = $this->report->summary($editionId);
        $tickets = $summary['revenue']['tickets'];
        $sponsors = $summary['revenue']['sponsors'];

        $resumo = [
            ['Indicador', 'Valor (R$)'],
            ['Ingressos: vendido', $this->reais($tickets['gross'])],
            ['Ingressos: reembolsos', $this->reais(-$tickets['refunded'])],
            ['Ingressos: taxas do gateway', $this->reais(-$tickets['fees'])],
            ['Ingressos: líquido', $this->reais($tickets['net'])],
            ['Patrocínio recebido', $this->reais($sponsors['received'])],
            ['Receita líquida', $this->reais($summary['revenue']['total'])],
            ['Despesas pagas', $this->reais(-$summary['expenses']['paid'])],
            ['Saldo', $this->reais($summary['balance'])],
            ['Patrocínio a receber', $this->reais($sponsors['outstanding'])],
            ['Despesas a pagar', $this->reais(-$summary['expenses']['pending'])],
            ['Previsão', $this->reais($summary['forecast'])],
        ];

        $contas = [['Conta', 'Titular', 'Tipo', 'Documento', 'Pedidos', 'Vendido (R$)', 'Reembolsos (R$)', 'Taxas (R$)', 'Líquido (R$)', 'Taxas estimadas']];

        foreach ($tickets['by_account'] as $line) {
            $account = $line['account'];
            $contas[] = [
                $account === null ? 'Sem conta' : $account['driver'].($account['retired'] ? ' (trocada)' : ''),
                self::text($account['holder_name'] ?? null),
                $account === null ? null : ($account['holder_type'] === 'company' ? 'CNPJ' : 'CPF'),
                $account['holder_document'] ?? null,
                $line['orders'],
                $this->reais($line['gross']),
                $this->reais($line['refunded']),
                $this->reais($line['fees']),
                $this->reais($line['net']),
                $line['estimated_fees'],
            ];
        }

        $orcamento = [['Categoria', 'Orçado (R$)', 'Comprometido (R$)', 'Pago (R$)', 'Disponível (R$)']];

        foreach ($summary['budget']['categories'] as $row) {
            $orcamento[] = [
                self::text($row['name']),
                $row['budget'] === null ? null : $this->reais($row['budget']),
                $this->reais($row['committed']),
                $this->reais($row['paid']),
                $row['remaining'] === null ? null : $this->reais($row['remaining']),
            ];
        }

        return [
            'Resumo' => $resumo,
            'Receita por conta' => $contas,
            'Orçamento' => $orcamento,
            'Despesas' => $this->expenses($editionId),
        ];
    }

    /**
     * @return list<list<string|int|float|null>>
     */
    public function expenses(string $editionId): array
    {
        $expenses = Expense::query()->forEdition($editionId)->with('category:id,name')->orderByRaw('due_on is null')->oldest('due_on')->oldest()->get();
        $suppliers = $this->suppliers->forEdition($editionId);
        $rows = [['Descrição', 'Categoria', 'Fornecedor', 'Valor (R$)', 'Vencimento', 'Pagamento', 'Situação', 'Comprovante']];

        foreach ($expenses as $expense) {
            $rows[] = [
                self::text($expense->description),
                self::text($expense->category->name),
                self::text($expense->edition_supplier_id === null ? null : ($suppliers[$expense->edition_supplier_id]['name'] ?? null)),
                $this->reais($expense->amount_cents),
                $expense->due_on?->toDateString(),
                $expense->paid_on?->toDateString(),
                $expense->overdue() ? 'Atrasada' : $expense->status->label(),
                $expense->receipt_path === null ? 'Não' : 'Sim',
            ];
        }

        return $rows;
    }

    private function reais(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
