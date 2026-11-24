import { computed, ref } from 'vue'
import financeService, { type AccountPayload, type ExpensePayload, type SummaryPayload } from '../services/financeService'
import { FinanceError } from '../services/financeError'
import locale from '../locales/pt-br'
import type { Category, Expense, ExpenseInput, ExpenseTotals, Failure, MutationResult, Summary, SupplierOption } from '../types'

const DRIVERS: Record<string, string> = { asaas: 'Asaas', mercadopago: 'Mercado Pago', fake: 'Teste' }

const accountLabel = (account: AccountPayload | null) => (account === null
  ? locale.noAccount
  : [DRIVERS[account.driver] ?? account.driver, account.holder_name, account.holder_document].filter(Boolean).join(' · '))

export const translateSummary = (payload: SummaryPayload): Summary => ({
  revenueCents: payload.revenue.total,
  ticketsGrossCents: payload.revenue.tickets.gross,
  ticketsRefundedCents: payload.revenue.tickets.refunded,
  ticketsFeesCents: payload.revenue.tickets.fees,
  ticketsNetCents: payload.revenue.tickets.net,
  sponsorsReceivedCents: payload.revenue.sponsors.received,
  sponsorsOutstandingCents: payload.revenue.sponsors.outstanding,
  accounts: payload.revenue.tickets.by_account.map((line) => ({
    accountLabel: accountLabel(line.account),
    retired: line.account?.retired ?? false,
    orders: line.orders,
    grossCents: line.gross,
    refundedCents: line.refunded,
    feesCents: line.fees,
    netCents: line.net,
    estimatedFees: line.estimated_fees > 0,
  })),
  paidCents: payload.expenses.paid,
  pendingCents: payload.expenses.pending,
  overdueCount: payload.expenses.overdue,
  balanceCents: payload.balance,
  forecastCents: payload.forecast,
  budget: payload.budget.categories.map((row) => ({
    id: row.id,
    name: row.name,
    budgetCents: row.budget,
    committedCents: row.committed,
    paidCents: row.paid,
    remainingCents: row.remaining,
    percentUsed: row.percent_used,
    overBudget: row.over_budget,
  })),
  budgetedCents: payload.budget.budgeted,
  committedCents: payload.budget.committed,
})

export const translateExpense = (payload: ExpensePayload): Expense => ({
  id: payload.id,
  description: payload.description,
  categoryId: payload.category.id,
  categoryName: payload.category.name,
  supplierId: payload.supplier?.id ?? null,
  supplierName: payload.supplier?.name ?? null,
  amountCents: payload.amount,
  dueOn: payload.due_on,
  paidOn: payload.paid_on,
  status: payload.status,
  statusLabel: payload.status_label,
  overdue: payload.overdue,
  notes: payload.notes,
  receipt: payload.receipt ? { name: payload.receipt.name, sizeBytes: payload.receipt.size_bytes } : null,
})

const FIELDS: Record<string, string> = {
  finance_category_id: 'categoryId', edition_supplier_id: 'supplierId', description: 'description', amount_cents: 'amount', due_on: 'dueOn', paid_on: 'paidOn',
  status: 'status', notes: 'notes', file: 'file', name: 'name', budget_cents: 'budget', category: 'category',
}

function failure(error: unknown): Failure {
  if (error instanceof FinanceError && error.statusCode === 422 && error.fieldErrors) {
    const fieldErrors = Object.fromEntries(Object.entries(error.fieldErrors).map(([key, messages]) => [FIELDS[key] ?? key, messages[0] ?? '']))
    return { ok: false, message: Object.values(fieldErrors)[0] ?? locale.genericError, fieldErrors }
  }
  return { ok: false, message: locale.genericError, fieldErrors: {} }
}

const blank = (text: string) => (text.trim() === '' ? null : text.trim())

/** Filtro da lista: `''` todas, `overdue` atrasadas, ou um status. */
export type ExpenseFilter = '' | 'overdue' | 'pending' | 'paid' | 'cancelled'

export function useFinance(editionId: string) {
  const summary = ref<Summary | null>(null)
  const categories = ref<Category[]>([])
  const suppliers = ref<SupplierOption[]>([])
  const expenses = ref<Expense[]>([])
  const totals = ref<ExpenseTotals | null>(null)
  const filter = ref<ExpenseFilter>('')
  const state = ref<'loading' | 'ready' | 'error'>('loading')
  const expensesState = ref<'loading' | 'ready' | 'error'>('loading')
  const hasBudget = computed(() => summary.value?.budget.some((row) => row.budgetCents !== null || row.committedCents > 0) ?? false)

  async function loadSummary() {
    const [shown, list] = await Promise.all([financeService.summary(editionId), financeService.categories(editionId)])
    summary.value = translateSummary(shown.data)
    categories.value = list.data.map((category) => ({ id: category.id, name: category.name, budgetCents: category.budget }))
  }

  async function loadExpenses() {
    expensesState.value = 'loading'
    try {
      const response = await financeService.expenses(editionId, {
        status: filter.value === 'overdue' ? '' : filter.value,
        overdue: filter.value === 'overdue',
        category: '',
      })
      expenses.value = response.data.map(translateExpense)
      totals.value = { count: response.totals.count, amountCents: response.totals.amount, paidCents: response.totals.paid, pendingCents: response.totals.pending }
      expensesState.value = 'ready'
    } catch {
      expensesState.value = 'error'
    }
  }

  async function load() {
    state.value = 'loading'
    try {
      await Promise.all([loadSummary(), loadExpenses()])
      state.value = 'ready'
    } catch {
      state.value = 'error'
    }
    financeService.suppliers(editionId)
      .then((response) => { suppliers.value = response.data.map((supplier) => ({ id: supplier.id, name: supplier.name })) })
      .catch(() => { suppliers.value = [] })
  }

  async function filterBy(next: ExpenseFilter) {
    filter.value = next
    await loadExpenses()
  }

  async function mutate(request: () => Promise<unknown>): Promise<MutationResult> {
    try {
      await request()
      await Promise.all([loadSummary(), loadExpenses()])
      return { ok: true }
    } catch (error) {
      return failure(error)
    }
  }

  const expenseBody = (input: ExpenseInput) => ({
    finance_category_id: input.categoryId,
    edition_supplier_id: input.supplierId || null,
    description: input.description.trim(),
    amount_cents: input.amountCents,
    due_on: input.dueOn || null,
    paid_on: input.status === 'paid' ? input.paidOn || null : null,
    status: input.status,
    notes: blank(input.notes),
  })

  /** Salva a despesa e, se veio arquivo, envia o comprovante em seguida. */
  async function saveExpense(expenseId: string | null, input: ExpenseInput, receipt: File | null): Promise<MutationResult> {
    let savedId = expenseId
    try {
      const saved = expenseId
        ? await financeService.updateExpense(editionId, expenseId, expenseBody(input))
        : await financeService.createExpense(editionId, expenseBody(input))
      savedId = saved.data.id
    } catch (error) {
      return failure(error)
    }
    return mutate(async () => {
      if (receipt && savedId) await financeService.uploadReceipt(editionId, savedId, receipt)
    })
  }

  const markPaid = (expense: Expense, paidOn: string) => mutate(() => financeService.updateExpense(editionId, expense.id, { status: 'paid', paid_on: paidOn }))
  const deleteExpense = (expense: Expense) => mutate(() => financeService.deleteExpense(editionId, expense.id))

  const saveCategory = (categoryId: string | null, name: string, budgetCents: number | null) => mutate(() => (categoryId
    ? financeService.updateCategory(editionId, categoryId, { name: name.trim(), budget_cents: budgetCents })
    : financeService.createCategory(editionId, { name: name.trim(), budget_cents: budgetCents })))
  const deleteCategory = (categoryId: string) => mutate(() => financeService.deleteCategory(editionId, categoryId))

  async function receiptUrl(expenseId: string): Promise<string | null> {
    try {
      return (await financeService.receiptUrl(editionId, expenseId)).data.url
    } catch {
      return null
    }
  }

  async function exportFile(format: 'xlsx' | 'csv'): Promise<Blob | null> {
    try {
      return await financeService.export(editionId, format)
    } catch {
      return null
    }
  }

  return {
    summary, categories, suppliers, expenses, totals, filter, state, expensesState, hasBudget,
    load, loadExpenses, filterBy, saveExpense, markPaid, deleteExpense, saveCategory, deleteCategory, receiptUrl, exportFile,
  }
}
