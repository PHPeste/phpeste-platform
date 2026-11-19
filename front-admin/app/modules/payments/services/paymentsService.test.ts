import { describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import paymentsService from './paymentsService'
import { PaymentsError } from './paymentsError'

vi.mock('@/infrastructure/http/HttpClient', () => ({
  httpClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn() },
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

describe('paymentsService', () => {
  it('chama as rotas do gateway da edição', async () => {
    vi.mocked(httpClient.get).mockResolvedValue({ data: [] })
    vi.mocked(httpClient.post).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.patch).mockResolvedValue({ data: {} })

    await paymentsService.accounts('ed-1')
    await paymentsService.verify('ed-1', 'a-1')
    await paymentsService.update('ed-1', 'a-1', { holder_name: 'PHP-PB', methods: ['pix'], max_installments: 1, pix_expiration_minutes: 30 })
    await paymentsService.events('ed-1', 2, '')
    await paymentsService.events('ed-1', 1, 'failed')
    await paymentsService.reprocess('ed-1', 'e-1')

    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/payments/accounts')
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/payments/accounts/a-1/verify')
    expect(httpClient.patch).toHaveBeenCalledWith('/editions/ed-1/payments/accounts/a-1', { body: expect.objectContaining({ holder_name: 'PHP-PB' }) })
    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/payments/webhook-events', { query: { page: 2 } })
    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/payments/webhook-events', { query: { page: 1, status: 'failed' } })
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/payments/webhook-events/e-1/reprocess')
  })

  it('converte o erro do HttpClient em PaymentsError com os campos', async () => {
    vi.mocked(httpClient.post).mockRejectedValue(new HttpClientError('inválido', 422, { api_key: ['O gateway recusou a chave de API.'] }))

    const error = await paymentsService.verify('ed-1', 'a-1').catch((caught: unknown) => caught)
    expect(error).toBeInstanceOf(PaymentsError)
    expect(error).toMatchObject({ statusCode: 422, fieldErrors: { api_key: ['O gateway recusou a chave de API.'] } })
  })
})
