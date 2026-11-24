import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { FinanceError } from './financeError'

export type ExpenseStatusValue = 'pending' | 'paid' | 'cancelled'

export interface AccountPayload {
  id: string
  driver: string
  holder_type: string
  holder_name: string
  holder_document: string | null
  retired: boolean
}

export interface SummaryPayload {
  revenue: {
    tickets: {
      gross: number
      refunded: number
      fees: number
      net: number
      by_account: Array<{ account: AccountPayload | null, orders: number, gross: number, refunded: number, fees: number, net: number, estimated_fees: number }>
    }
    sponsors: { received: number, outstanding: number }
    total: number
  }
  expenses: { paid: number, pending: number, overdue: number }
  balance: number
  forecast: number
  budget: {
    categories: Array<{ id: string, name: string, budget: number | null, committed: number, paid: number, remaining: number | null, percent_used: number | null, over_budget: boolean }>
    budgeted: number
    committed: number
    paid: number
  }
}

export interface CategoryPayload { id: string, name: string, budget: number | null, position: number }

export interface ExpensePayload {
  id: string
  description: string
  category: { id: string, name: string }
  supplier: { id: string, name: string } | null
  amount: number
  due_on: string | null
  paid_on: string | null
  status: ExpenseStatusValue
  status_label: string
  overdue: boolean
  notes: string | null
  receipt: { name: string, mime_type: string, size_bytes: number } | null
}

export interface ExpensesPayload {
  data: ExpensePayload[]
  totals: { count: number, amount: number, paid: number, pending: number }
}

export interface SupplierOption { id: string, name: string, status: string, contracted_cents: number | null }

export interface ExpenseBody {
  finance_category_id: string
  edition_supplier_id: string | null
  description: string
  amount_cents: number
  due_on: string | null
  paid_on: string | null
  status: ExpenseStatusValue
  notes: string | null
}

export interface ExpenseQuery { status: string, overdue: boolean, category: string }

const base = (editionId: string) => `/editions/${editionId}/finance`

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new FinanceError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new FinanceError('Request failed')
  }
}

export default {
  summary: (editionId: string) => call(() => httpClient.get<{ data: SummaryPayload }>(`${base(editionId)}/summary`)),
  categories: (editionId: string) => call(() => httpClient.get<{ data: CategoryPayload[] }>(`${base(editionId)}/categories`)),
  createCategory: (editionId: string, body: { name: string, budget_cents: number | null }) => call(() => httpClient.post<{ data: CategoryPayload }>(`${base(editionId)}/categories`, { body })),
  updateCategory: (editionId: string, categoryId: string, body: { name?: string, budget_cents?: number | null }) =>
    call(() => httpClient.patch<{ data: CategoryPayload }>(`${base(editionId)}/categories/${categoryId}`, { body })),
  deleteCategory: (editionId: string, categoryId: string) => call(() => httpClient.delete<null>(`${base(editionId)}/categories/${categoryId}`)),
  suppliers: (editionId: string) => call(() => httpClient.get<{ data: SupplierOption[] }>(`${base(editionId)}/suppliers`)),
  expenses: (editionId: string, query: ExpenseQuery) => call(() => httpClient.get<ExpensesPayload>(`${base(editionId)}/expenses`, {
    query: {
      ...(query.status ? { status: query.status } : {}),
      ...(query.overdue ? { overdue: 1 } : {}),
      ...(query.category ? { finance_category_id: query.category } : {}),
    },
  })),
  createExpense: (editionId: string, body: ExpenseBody) => call(() => httpClient.post<{ data: ExpensePayload }>(`${base(editionId)}/expenses`, { body })),
  updateExpense: (editionId: string, expenseId: string, body: Partial<ExpenseBody>) => call(() => httpClient.patch<{ data: ExpensePayload }>(`${base(editionId)}/expenses/${expenseId}`, { body })),
  deleteExpense: (editionId: string, expenseId: string) => call(() => httpClient.delete<null>(`${base(editionId)}/expenses/${expenseId}`)),
  uploadReceipt: (editionId: string, expenseId: string, file: File) => {
    const body = new FormData()
    body.append('file', file)
    return call(() => httpClient.post<{ data: ExpensePayload }>(`${base(editionId)}/expenses/${expenseId}/receipt`, { body }))
  },
  receiptUrl: (editionId: string, expenseId: string) => call(() => httpClient.get<{ data: { url: string, expires_at: string } }>(`${base(editionId)}/expenses/${expenseId}/receipt/url`)),
  export: (editionId: string, format: 'xlsx' | 'csv') => call(() => httpClient.get<Blob>(`${base(editionId)}/export`, { query: format === 'csv' ? { format: 'csv' } : {}, as: 'blob' })),
}
