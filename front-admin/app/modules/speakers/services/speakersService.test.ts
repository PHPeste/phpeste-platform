import { describe, expect, it, vi } from 'vitest'
import { httpClient } from '@/infrastructure/http/HttpClient'
import speakersService from './speakersService'

vi.mock('@/infrastructure/http/HttpClient', () => ({
  httpClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn() },
  HttpClientError: class extends Error {},
}))

describe('speakersService', () => {
  it('chama as rotas do quadro, do detalhe e das ações', async () => {
    vi.mocked(httpClient.get).mockResolvedValue({ data: [] })
    vi.mocked(httpClient.post).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.patch).mockResolvedValue({ data: {} })

    await speakersService.list('ed-1')
    await speakersService.show('ed-1', 'u-1')
    await speakersService.remind('ed-1', null)
    await speakersService.remind('ed-1', 'p-1')
    await speakersService.attendance('ed-1', 'u-1', true)
    await speakersService.promote('ed-1', 'p-3')
    await speakersService.updateLogistics('ed-1', 'u-1', { travel_paid_by_organization: true, lodging_paid_by_organization: false, organization_notes: null })
    await speakersService.extendDeadline('ed-1', 'p-1', '2027-05-10T02:59:59.000Z')

    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/cfp/speakers')
    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/cfp/speakers/u-1')
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/cfp/speakers/remind', { body: {} })
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/cfp/speakers/remind', { body: { proposal_id: 'p-1' } })
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/cfp/speakers/u-1/attendance', { body: { present: true } })
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/cfp/promotions', { body: { proposal_id: 'p-3' } })
    expect(httpClient.patch).toHaveBeenCalledWith('/editions/ed-1/cfp/logistics/u-1', { body: { travel_paid_by_organization: true, lodging_paid_by_organization: false, organization_notes: null } })
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/cfp/confirmations/p-1/extend', { body: { deadline_at: '2027-05-10T02:59:59.000Z' } })
  })
})
