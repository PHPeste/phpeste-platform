import { describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import attendeesService from './attendeesService'
import { AttendeesError } from './attendeesError'

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

describe('attendeesService', () => {
  it('chama as rotas de ingressos da edição, mandando o filtro como parâmetro próprio', async () => {
    vi.mocked(httpClient.get).mockResolvedValue({ data: [] })
    vi.mocked(httpClient.post).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.patch).mockResolvedValue({ data: {} })

    await attendeesService.list('ed-1', { page: 2, filter: 'missing', q: '  ana ' })
    await attendeesService.list('ed-1', { page: 1, filter: '', q: '' })
    await attendeesService.show('ed-1', 't-1')
    await attendeesService.update('ed-1', 't-1', { badge_name: 'Aninha' })
    await attendeesService.resend('ed-1', 't-1')
    await attendeesService.reminderPreview('ed-1')
    await attendeesService.remind('ed-1', 3)
    await attendeesService.badges('ed-1')

    const base = '/editions/ed-1/ticketing/tickets'
    expect(httpClient.get).toHaveBeenCalledWith(base, { query: { page: 2, missing: 1, q: 'ana' } })
    expect(httpClient.get).toHaveBeenCalledWith(base, { query: { page: 1 } })
    expect(httpClient.get).toHaveBeenCalledWith(`${base}/t-1`)
    expect(httpClient.patch).toHaveBeenCalledWith(`${base}/t-1`, { body: { badge_name: 'Aninha' } })
    expect(httpClient.post).toHaveBeenCalledWith(`${base}/t-1/resend`)
    expect(httpClient.get).toHaveBeenCalledWith(`${base}/data-reminder`)
    expect(httpClient.post).toHaveBeenCalledWith(`${base}/data-reminder`, { body: { confirm: true, recipients: 3 } })
    expect(httpClient.get).toHaveBeenCalledWith(`${base}/badges`, { as: 'blob' })
  })

  it('troca o erro HTTP pelo erro do módulo, com os campos', async () => {
    vi.mocked(httpClient.patch).mockRejectedValue(new HttpClientError('Inválido', 422, { badge_name: ['Longo demais'] }))

    await expect(attendeesService.update('ed-1', 't-1', {})).rejects.toMatchObject({ name: 'AttendeesError', statusCode: 422, fieldErrors: { badge_name: ['Longo demais'] } })
    await expect(attendeesService.update('ed-1', 't-1', {})).rejects.toBeInstanceOf(AttendeesError)
  })
})
