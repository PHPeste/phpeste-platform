import { describe, expect, it, vi } from 'vitest'
import { httpClient } from '@/infrastructure/http/HttpClient'
import pingService from './pingService'

vi.mock('@/infrastructure/http/HttpClient', () => ({
  httpClient: { get: vi.fn() },
}))

describe('pingService.ping', () => {
  it('chama GET /ping e devolve o payload tipado pelo contrato OpenAPI', async () => {
    const payload = { status: 'ok', request_id: 'abc-123' }
    vi.mocked(httpClient.get).mockResolvedValueOnce(payload)

    const result = await pingService.ping()

    expect(httpClient.get).toHaveBeenCalledWith('/ping')
    expect(result).toEqual(payload)
  })
})
