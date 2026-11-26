import { describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import communicationService from './communicationService'
import { CommunicationError } from './communicationError'

vi.mock('@/infrastructure/http/HttpClient', () => ({
  httpClient: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
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

describe('communicationService', () => {
  it('chama as rotas dos modelos de e-mail da edição', async () => {
    vi.mocked(httpClient.get).mockResolvedValue({ data: [] })
    vi.mocked(httpClient.post).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.put).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.delete).mockResolvedValue({ data: {} })
    const content = { subject: 'Oi {nome}', body: 'Texto' }

    await communicationService.list('ed-1')
    await communicationService.save('ed-1', 'order_confirmed', content)
    await communicationService.restore('ed-1', 'order_confirmed')
    await communicationService.preview('ed-1', 'order_confirmed', content)
    await communicationService.sendTest('ed-1', 'order_confirmed', content)

    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/emails/templates')
    expect(httpClient.put).toHaveBeenCalledWith('/editions/ed-1/emails/templates/order_confirmed', { body: content })
    expect(httpClient.delete).toHaveBeenCalledWith('/editions/ed-1/emails/templates/order_confirmed')
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/emails/templates/order_confirmed/preview', { body: content })
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/emails/templates/order_confirmed/test', { body: content })
  })

  it('troca o erro HTTP pelo erro do módulo', async () => {
    vi.mocked(httpClient.put).mockRejectedValue(new HttpClientError('x', 422, { body: ['Variável desconhecida'] }))
    await expect(communicationService.save('ed-1', 'order_confirmed', { subject: 'a', body: 'b' })).rejects.toMatchObject({ name: 'CommunicationError', statusCode: 422, fieldErrors: { body: ['Variável desconhecida'] } })
    vi.mocked(httpClient.get).mockRejectedValue(new Error('offline'))
    await expect(communicationService.list('ed-1')).rejects.toBeInstanceOf(CommunicationError)
  })
})
