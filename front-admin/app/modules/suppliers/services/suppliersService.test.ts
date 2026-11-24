import { describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import suppliersService from './suppliersService'
import { SuppliersError } from './suppliersError'

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

describe('suppliersService', () => {
  it('chama as rotas de fornecedores da edição, mandando só os filtros preenchidos', async () => {
    vi.mocked(httpClient.get).mockResolvedValue({ data: [] })
    vi.mocked(httpClient.post).mockResolvedValue({ data: {} })

    await suppliersService.list('ed-1', { status: 'hired', service: '', q: '  buffet ' })
    await suppliersService.changeStatus('ed-1', 'l-1', { status: 'hired', quote_id: 'q-1' })
    await suppliersService.recordQuote('ed-1', 'l-1', { amount_cents: 960_000, description: 'Coffee break', received_on: '2027-03-10', valid_until: null })
    await suppliersService.directory('ed-1', ' som ')
    await suppliersService.link('ed-1', 's-9', null)
    await suppliersService.attachmentUrl('ed-1', 'a-1')

    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/suppliers', { query: { status: 'hired', q: 'buffet' } })
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/suppliers/l-1/status', { body: { status: 'hired', quote_id: 'q-1' } })
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/suppliers/l-1/quotes', expect.objectContaining({ body: expect.objectContaining({ amount_cents: 960_000 }) }))
    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/suppliers/directory', { query: { q: 'som' } })
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/suppliers/directory/s-9', { body: { owner_id: null } })
    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/suppliers/attachments/a-1/url')
  })

  it('troca o erro HTTP pelo erro do módulo, com os campos', async () => {
    vi.mocked(httpClient.post).mockRejectedValue(new HttpClientError('Inválido', 422, { reason: ['Conte por que'] }))

    await expect(suppliersService.changeStatus('ed-1', 'l-1', { status: 'declined' })).rejects.toMatchObject({ name: 'SuppliersError', statusCode: 422, fieldErrors: { reason: ['Conte por que'] } })
    await expect(suppliersService.changeStatus('ed-1', 'l-1', { status: 'declined' })).rejects.toBeInstanceOf(SuppliersError)
  })
})
