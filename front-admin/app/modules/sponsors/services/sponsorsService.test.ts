import { describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import sponsorsService from './sponsorsService'
import { SponsorsError } from './sponsorsError'

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

describe('sponsorsService', () => {
  it('chama as rotas de patrocinadores da edição', async () => {
    vi.mocked(httpClient.get).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.post).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.patch).mockResolvedValue({ data: {} })

    await sponsorsService.board('ed-1')
    await sponsorsService.setGoal('ed-1', 6_000_000)
    await sponsorsService.move('ed-1', 'd-1', { stage: 'closed', position: 2, confirm: true })
    await sponsorsService.recordPayment('ed-1', 'd-1', { amount_cents: 400_000, paid_on: '2027-03-10', note: null })
    await sponsorsService.attachmentUrl('ed-1', 'a-1')
    await sponsorsService.updateDeliverable('ed-1', 'e-1', { status: 'done' })

    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/sponsors/board')
    expect(httpClient.patch).toHaveBeenCalledWith('/editions/ed-1/sponsors/settings', { body: { goal_cents: 6_000_000 } })
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/sponsors/sponsorships/d-1/move', { body: { stage: 'closed', position: 2, confirm: true } })
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/sponsors/sponsorships/d-1/payments', { body: { amount_cents: 400_000, paid_on: '2027-03-10', note: null } })
    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/sponsors/attachments/a-1/url')
    expect(httpClient.patch).toHaveBeenCalledWith('/editions/ed-1/sponsors/deliverables/e-1', { body: { status: 'done' } })
  })

  it('envia o anexo como formulário com o tipo e o arquivo', async () => {
    vi.mocked(httpClient.post).mockResolvedValue({ data: {} })
    const file = new File(['%PDF'], 'contrato.pdf', { type: 'application/pdf' })

    await sponsorsService.upload('ed-1', 's-1', 'contract', file)

    const [url, options] = vi.mocked(httpClient.post).mock.calls.at(-1)!
    const body = (options as { body: FormData }).body
    expect(url).toBe('/editions/ed-1/sponsors/companies/s-1/attachments')
    expect(body.get('kind')).toBe('contract')
    expect((body.get('file') as File).name).toBe('contrato.pdf')
  })

  it('troca o erro HTTP pelo erro do módulo, com os campos', async () => {
    vi.mocked(httpClient.post).mockRejectedValue(new HttpClientError('Inválido', 422, { confirm: ['Confirme'] }))

    await expect(sponsorsService.move('ed-1', 'd-1', { stage: 'closed' })).rejects.toMatchObject({ name: 'SponsorsError', statusCode: 422, fieldErrors: { confirm: ['Confirme'] } })
    await expect(sponsorsService.move('ed-1', 'd-1', { stage: 'closed' })).rejects.toBeInstanceOf(SponsorsError)
  })
})
