import { describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import checkoutService from './checkoutService'
import { CheckoutError } from './checkoutError'

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

describe('checkoutService', () => {
  it('chama as rotas públicas e do checkout com a chave de idempotência e o token do pedido', async () => {
    vi.mocked(httpClient.get).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.post).mockResolvedValue({ data: {} })
    const body = { buyer: { name: 'Ada', email: 'ada@x.dev', document: null }, coupon: null, items: [] }

    await checkoutService.catalog('ed-1')
    await checkoutService.checkCoupon('ed-1', 'SERTAO10', [{ ticket_type_id: 't-1', quantity: 2 }])
    await checkoutService.placeOrder('ed-1', body, 'key-1')
    await checkoutService.order('ed-1', 'o-1', 'tok')
    await checkoutService.cancel('ed-1', 'o-1', 'tok')

    expect(httpClient.get).toHaveBeenCalledWith('/public/editions/ed-1/tickets')
    expect(httpClient.post).toHaveBeenCalledWith('/public/editions/ed-1/coupons/check', { body: { code: 'SERTAO10', items: [{ ticket_type_id: 't-1', quantity: 2 }] } })
    expect(httpClient.post).toHaveBeenCalledWith('/checkout/editions/ed-1/orders', { body, headers: { 'Idempotency-Key': 'key-1' } })
    expect(httpClient.get).toHaveBeenCalledWith('/checkout/editions/ed-1/orders/o-1', { headers: { 'X-Order-Token': 'tok' } })
    expect(httpClient.post).toHaveBeenCalledWith('/checkout/editions/ed-1/orders/o-1/cancel', { headers: { 'X-Order-Token': 'tok' } })
  })

  it('pagamento: quem recebe, iniciar com a chave de idempotência, consultar e ingressos, sempre com o token do pedido', async () => {
    vi.mocked(httpClient.get).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.post).mockResolvedValue({ data: {} })

    await checkoutService.receiver('ed-1')
    await checkoutService.startPayment('ed-1', 'o-1', 'tok', { method: 'pix', installments: 1 }, 'key-9')
    await checkoutService.payment('ed-1', 'o-1', 'tok')
    await checkoutService.tickets('ed-1', 'o-1', 'tok')

    expect(httpClient.get).toHaveBeenCalledWith('/public/editions/ed-1/payments/receiver')
    expect(httpClient.post).toHaveBeenCalledWith('/checkout/editions/ed-1/orders/o-1/payment', { body: { method: 'pix', installments: 1 }, headers: { 'X-Order-Token': 'tok', 'Idempotency-Key': 'key-9' } })
    expect(httpClient.get).toHaveBeenCalledWith('/checkout/editions/ed-1/orders/o-1/payment', { headers: { 'X-Order-Token': 'tok' } })
    expect(httpClient.get).toHaveBeenCalledWith('/checkout/editions/ed-1/orders/o-1/tickets', { headers: { 'X-Order-Token': 'tok' } })
  })

  it('troca o erro HTTP pelo erro do módulo', async () => {
    vi.mocked(httpClient.post).mockRejectedValue(new HttpClientError('x', 422, { coupon: ['Cupom não encontrado.'] }))
    await expect(checkoutService.checkCoupon('ed-1', 'X', [])).rejects.toMatchObject({ name: 'CheckoutError', statusCode: 422, fieldErrors: { coupon: ['Cupom não encontrado.'] } })
    vi.mocked(httpClient.get).mockRejectedValue(new Error('offline'))
    await expect(checkoutService.catalog('ed-1')).rejects.toBeInstanceOf(CheckoutError)
  })
})
