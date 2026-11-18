import { describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import communitiesService from './communitiesService'
import { CommunitiesError } from './communitiesError'

vi.mock('@/infrastructure/http/HttpClient', () => ({
  httpClient: { post: vi.fn(), get: vi.fn() },
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

describe('communitiesService', () => {
  it('cria a edição em branco com os campos em snake_case e vazios como nulo', async () => {
    vi.mocked(httpClient.post).mockResolvedValueOnce({ data: {} })

    await communitiesService.createEdition('c1', { name: 'PHPeste 2028', slug: '2028', startsOn: '2028-10-06', endsOn: '', city: 'Natal', state: '' })

    expect(httpClient.post).toHaveBeenCalledWith('/communities/c1/editions', {
      body: { name: 'PHPeste 2028', slug: '2028', starts_on: '2028-10-06', ends_on: null, city: 'Natal', state: null },
    })
  })

  it('pede a cópia a partir da edição de origem e acompanha pelo id', async () => {
    vi.mocked(httpClient.post).mockResolvedValueOnce({ data: {} })
    vi.mocked(httpClient.get).mockResolvedValueOnce({ data: {} })

    await communitiesService.clone({ sourceEditionId: 'ed-27', name: 'PHPeste 2028', slug: '2028', parts: ['team'] })
    await communitiesService.cloneStatus('clone-1')

    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-27/clone', { body: { name: 'PHPeste 2028', slug: '2028', parts: ['team'] } })
    expect(httpClient.get).toHaveBeenCalledWith('/edition-clones/clone-1')
  })

  it('embrulha falhas num CommunitiesError com o status', async () => {
    vi.mocked(httpClient.post).mockRejectedValueOnce(new HttpClientError('x', 403))

    const promise = communitiesService.createCommunity({ name: 'X', slug: 'x' })
    await expect(promise).rejects.toBeInstanceOf(CommunitiesError)
    await expect(promise).rejects.toMatchObject({ statusCode: 403 })
  })
})
