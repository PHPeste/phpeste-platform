import { beforeEach, describe, expect, it, vi } from 'vitest'
import financeService from '../services/financeService'
import { FinanceError } from '../services/financeError'
import { useFinance } from './useFinance'
import { expensePayload, summaryPayload } from './fixtures'

vi.mock('../services/financeService', () => ({
  default: {
    summary: vi.fn(), categories: vi.fn(), suppliers: vi.fn(), expenses: vi.fn(), createExpense: vi.fn(), updateExpense: vi.fn(), deleteExpense: vi.fn(),
    uploadReceipt: vi.fn(), receiptUrl: vi.fn(), createCategory: vi.fn(), updateCategory: vi.fn(), deleteCategory: vi.fn(), export: vi.fn(),
  },
}))

const input = { categoryId: 'cat-local', supplierId: '', description: ' Sinal do espaço ', amountCents: 900_000, dueOn: '2027-07-10', paidOn: '2027-07-09', status: 'pending' as const, notes: ' ' }

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(financeService.summary).mockResolvedValue(summaryPayload() as never)
  vi.mocked(financeService.categories).mockResolvedValue({ data: [{ id: 'cat-local', name: 'Local', budget: 1_800_000, position: 0 }] } as never)
  vi.mocked(financeService.suppliers).mockResolvedValue({ data: [{ id: 'l-1', name: 'Espaço Mar Azul', status: 'hired', contracted_cents: 1_800_000 }] } as never)
  vi.mocked(financeService.expenses).mockResolvedValue({ data: [expensePayload('e-1', 'Sinal do espaço', 'paid', { receipt: { name: 'nota.pdf', mime_type: 'application/pdf', size_bytes: 1024 } })], totals: { count: 1, amount: 900_000, paid: 900_000, pending: 0 } } as never)
})

describe('useFinance', () => {
  it('traduz indicadores, receita por conta e orçamento por categoria', async () => {
    const finance = useFinance('ed-1')
    await finance.load()

    expect(finance.state.value).toBe('ready')
    expect(finance.summary.value).toMatchObject({ revenueCents: 1_970_000, paidCents: 1_140_000, balanceCents: 830_000, forecastCents: -2_390_000, overdueCount: 1 })
    expect(finance.summary.value?.accounts[0]).toMatchObject({ accountLabel: 'Asaas · Associação PHP-PB · **.***.***/0001-90', netCents: 1_820_000, estimatedFees: false })
    expect(finance.summary.value?.budget[1]).toMatchObject({ name: 'Brindes', overBudget: true, remainingCents: -40_000 })
    expect(finance.expenses.value[0]).toMatchObject({ amountCents: 900_000, supplierName: 'Espaço Mar Azul', receipt: { name: 'nota.pdf' } })
    expect(finance.totals.value).toEqual({ count: 1, amountCents: 900_000, paidCents: 900_000, pendingCents: 0 })
  })

  it('"Atrasadas" vira overdue=1, os outros chips viram status', async () => {
    const finance = useFinance('ed-1')
    await finance.filterBy('overdue')
    expect(financeService.expenses).toHaveBeenLastCalledWith('ed-1', { status: '', overdue: true, category: '' })
    await finance.filterBy('paid')
    expect(financeService.expenses).toHaveBeenLastCalledWith('ed-1', { status: 'paid', overdue: false, category: '' })
  })

  it('salva a despesa e envia o comprovante depois; despesa a pagar não manda data de pagamento', async () => {
    vi.mocked(financeService.createExpense).mockResolvedValue({ data: { id: 'e-9' } } as never)
    vi.mocked(financeService.uploadReceipt).mockResolvedValue({ data: {} } as never)
    const finance = useFinance('ed-1')
    const file = new File(['%PDF'], 'nota.pdf')

    expect(await finance.saveExpense(null, input, file)).toEqual({ ok: true })
    expect(financeService.createExpense).toHaveBeenCalledWith('ed-1', {
      finance_category_id: 'cat-local', edition_supplier_id: null, description: 'Sinal do espaço', amount_cents: 900_000, due_on: '2027-07-10', paid_on: null, status: 'pending', notes: null,
    })
    expect(financeService.uploadReceipt).toHaveBeenCalledWith('ed-1', 'e-9', file)
    expect(financeService.summary).toHaveBeenCalled()
  })

  it('erro de validação vira mensagem do campo', async () => {
    vi.mocked(financeService.updateCategory).mockRejectedValue(new FinanceError('x', 422, { name: ['Já existe uma categoria com esse nome.'] }))
    const finance = useFinance('ed-1')

    expect(await finance.saveCategory('cat-local', 'Local', 1_000_000)).toEqual({ ok: false, message: 'Já existe uma categoria com esse nome.', fieldErrors: { name: 'Já existe uma categoria com esse nome.' } })
  })
})
