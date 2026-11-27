import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import accountService from './accountService'
import { AccountError } from './accountError'

vi.mock('@/infrastructure/http/HttpClient', async (original) => ({
  ...(await original<typeof import('@/infrastructure/http/HttpClient')>()),
  httpClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn() },
}))

beforeEach(() => vi.clearAllMocks())

describe('accountService', () => {
  it('fala com a área do participante da edição', async () => {
    vi.mocked(httpClient.get).mockResolvedValue({ data: [], meta: { email_verified: true } })
    vi.mocked(httpClient.post).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.patch).mockResolvedValue({ data: {} })

    await accountService.tickets('ed-1')
    await accountService.orders('ed-1')
    await accountService.update('ed-1', 'tk-1', { badge_name: 'Ada' })
    await accountService.transfer('ed-1', 'tk-1', { name: 'Bia', email: 'bia@x.dev' })

    expect(httpClient.get).toHaveBeenNthCalledWith(1, '/attendee/editions/ed-1/tickets')
    expect(httpClient.get).toHaveBeenNthCalledWith(2, '/attendee/editions/ed-1/orders')
    expect(httpClient.patch).toHaveBeenCalledWith('/attendee/editions/ed-1/tickets/tk-1', { body: { badge_name: 'Ada' } })
    expect(httpClient.post).toHaveBeenCalledWith('/attendee/editions/ed-1/tickets/tk-1/transfer', { body: { name: 'Bia', email: 'bia@x.dev' } })
  })

  it('embrulha o erro HTTP no erro do módulo', async () => {
    vi.mocked(httpClient.get).mockRejectedValue(new HttpClientError('nope', 404))
    await expect(accountService.ticket('ed-1', 'tk-1')).rejects.toBeInstanceOf(AccountError)
    await expect(accountService.ticket('ed-1', 'tk-1')).rejects.toMatchObject({ statusCode: 404 })
  })
})
