import { describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import ticketsService from './ticketsService'
import { TicketsError } from './ticketsError'

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

describe('ticketsService', () => {
  it('chama as rotas de tipos, lotes, cupons e regras da edição', async () => {
    vi.mocked(httpClient.get).mockResolvedValue({ data: [] })
    vi.mocked(httpClient.post).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.patch).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.delete).mockResolvedValue(null)

    await ticketsService.types('ed-1')
    await ticketsService.updateType('ed-1', 'tt-1', { position: 2 })
    await ticketsService.createBatch('ed-1', 'tt-1', { name: '1º lote', price_cents: 15000, quantity: 100, starts_at: null, ends_at: null, on_sell_out: 'next' })
    await ticketsService.closeBatch('ed-1', 'tt-1', 'b-1')
    await ticketsService.deleteBatch('ed-1', 'tt-1', 'b-1')
    await ticketsService.coupons('ed-1')
    await ticketsService.deleteCoupon('ed-1', 'c-1')
    await ticketsService.updateSettings('ed-1', { capacity: 400, reservation_minutes: 30, max_tickets_per_buyer: null, gateway_fee_basis_points: 499 })

    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/ticketing/ticket-types')
    expect(httpClient.patch).toHaveBeenCalledWith('/editions/ed-1/ticketing/ticket-types/tt-1', { body: { position: 2 } })
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/ticketing/ticket-types/tt-1/batches', expect.objectContaining({ body: expect.objectContaining({ price_cents: 15000 }) }))
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/ticketing/ticket-types/tt-1/batches/b-1/close')
    expect(httpClient.delete).toHaveBeenCalledWith('/editions/ed-1/ticketing/ticket-types/tt-1/batches/b-1')
    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/ticketing/coupons')
    expect(httpClient.delete).toHaveBeenCalledWith('/editions/ed-1/ticketing/coupons/c-1')
    expect(httpClient.patch).toHaveBeenCalledWith('/editions/ed-1/ticketing/settings', { body: expect.objectContaining({ gateway_fee_basis_points: 499 }) })
  })

  it('embrulha o erro da API num TicketsError', async () => {
    vi.mocked(httpClient.post).mockRejectedValueOnce(new HttpClientError('x', 422, { name: ['O campo nome já está sendo utilizado.'] }))

    const error = await ticketsService.createType('ed-1', {} as never).catch((e: unknown) => e)
    expect(error).toBeInstanceOf(TicketsError)
    expect(error).toMatchObject({ statusCode: 422 })
  })
})
