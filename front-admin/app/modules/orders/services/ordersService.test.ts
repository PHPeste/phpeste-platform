import { describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import ordersService from './ordersService'
import { OrdersError } from './ordersError'

vi.mock('@/infrastructure/http/HttpClient', () => ({
  httpClient: { get: vi.fn(), post: vi.fn() },
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

describe('ordersService', () => {
  it('chama as rotas de pedidos da edição, mandando só os filtros preenchidos', async () => {
    vi.mocked(httpClient.get).mockResolvedValue({ data: [] })
    vi.mocked(httpClient.post).mockResolvedValue({ data: {} })

    await ordersService.list('ed-1', { page: 2, status: '', q: '  ana ' })
    await ordersService.history('ed-1', 'o-1')
    await ordersService.markPaid('ed-1', 'o-1', 'Transferência recebida')
    await ordersService.remind('ed-1', 'o-1')
    await ordersService.refund('ed-1', 'o-1', { amount_cents: null, reason: 'buyer_request', note: null, cancel_tickets: true, item_ids: null })
    await ordersService.settleRefund('ed-1', 'o-1', 'r-1', true)
    await ordersService.exportCsv('ed-1', { status: 'paid', q: '' })

    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/ticketing/orders', { query: { page: 2, q: 'ana' } })
    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/ticketing/orders/o-1/history')
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/ticketing/orders/o-1/mark-paid', { body: { justification: 'Transferência recebida' } })
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/ticketing/orders/o-1/payment-reminder')
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/ticketing/orders/o-1/refunds', expect.objectContaining({ body: expect.objectContaining({ cancel_tickets: true }) }))
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/ticketing/orders/o-1/refunds/r-1/settle', { body: { completed: true } })
    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/ticketing/orders/export', { query: { status: 'paid' }, as: 'blob' })
  })

  it('troca o erro HTTP pelo erro do módulo, com os campos', async () => {
    vi.mocked(httpClient.post).mockRejectedValue(new HttpClientError('Inválido', 422, { justification: ['Explique'] }))

    await expect(ordersService.markPaid('ed-1', 'o-1', '')).rejects.toMatchObject({ name: 'OrdersError', statusCode: 422, fieldErrors: { justification: ['Explique'] } })
    await expect(ordersService.markPaid('ed-1', 'o-1', '')).rejects.toBeInstanceOf(OrdersError)
  })
})
