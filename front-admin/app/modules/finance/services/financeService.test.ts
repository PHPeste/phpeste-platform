import { describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import financeService from './financeService'
import { FinanceError } from './financeError'

vi.mock('@/infrastructure/http/HttpClient', () => ({
  httpClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  HttpClientError: class extends Error {
    statusCode: number | null
    fieldErrors: Record<string, string[]> | null

    constructor(message: string, statusCode: number | null = null, fieldErrors: Record<string, string[]> | null = null) {
      super(message)
      this.statusCode = statusCode
      this.fieldErrors = fieldErrors
    }
  },
}))

describe('financeService', () => {
  it('chama as rotas de finanças da edição', async () => {
    vi.mocked(httpClient.get).mockResolvedValue({ data: [] })
    vi.mocked(httpClient.patch).mockResolvedValue({ data: {} })

    await financeService.summary('ed-1')
    await financeService.expenses('ed-1', { status: '', overdue: true, category: '' })
    await financeService.expenses('ed-1', { status: 'paid', overdue: false, category: 'cat-1' })
    await financeService.updateCategory('ed-1', 'cat-1', { budget_cents: 1_800_000 })
    await financeService.export('ed-1', 'xlsx')
    await financeService.export('ed-1', 'csv')

    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/finance/summary')
    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/finance/expenses', { query: { overdue: 1 } })
    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/finance/expenses', { query: { status: 'paid', finance_category_id: 'cat-1' } })
    expect(httpClient.patch).toHaveBeenCalledWith('/editions/ed-1/finance/categories/cat-1', { body: { budget_cents: 1_800_000 } })
    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/finance/export', { query: {}, as: 'blob' })
    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/finance/export', { query: { format: 'csv' }, as: 'blob' })
  })

  it('envia o comprovante como formulário e troca o erro HTTP pelo do módulo', async () => {
    vi.mocked(httpClient.post).mockResolvedValueOnce({ data: {} })
    await financeService.uploadReceipt('ed-1', 'e-1', new File(['%PDF'], 'nota.pdf'))
    const [url, options] = vi.mocked(httpClient.post).mock.calls.at(-1)!
    expect(url).toBe('/editions/ed-1/finance/expenses/e-1/receipt')
    expect(((options as { body: FormData }).body.get('file') as File).name).toBe('nota.pdf')

    vi.mocked(httpClient.post).mockRejectedValue(new HttpClientError('x', 403))
    await expect(financeService.createExpense('ed-1', {} as never)).rejects.toBeInstanceOf(FinanceError)
  })
})
