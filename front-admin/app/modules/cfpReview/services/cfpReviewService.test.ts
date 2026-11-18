import { describe, expect, it, vi } from 'vitest'
import { httpClient } from '@/infrastructure/http/HttpClient'
import cfpReviewService from './cfpReviewService'

vi.mock('@/infrastructure/http/HttpClient', () => ({
  httpClient: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
  HttpClientError: class extends Error {},
}))

describe('cfpReviewService', () => {
  it('chama as rotas de revisão, quadro, atribuição e exportação', async () => {
    vi.mocked(httpClient.get).mockResolvedValue({ data: [] })
    vi.mocked(httpClient.post).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.put).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.delete).mockResolvedValue(null)

    await cfpReviewService.queue('ed-1')
    await cfpReviewService.evaluation('ed-1')
    await cfpReviewService.saveReview('ed-1', 'p-1', { scores: { c: 4 }, comment: null, submit: true })
    await cfpReviewService.assign('ed-1', 'p-1', 'r-1')
    await cfpReviewService.unassign('ed-1', 'p-1', 'r-1')
    await cfpReviewService.autoAssign('ed-1', 3)
    await cfpReviewService.exportCsv('ed-1')

    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/cfp/reviews/queue')
    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/cfp/evaluation')
    expect(httpClient.put).toHaveBeenCalledWith('/editions/ed-1/cfp/proposals/p-1/review', { body: { scores: { c: 4 }, comment: null, submit: true } })
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/cfp/proposals/p-1/assignments', { body: { reviewer_id: 'r-1' } })
    expect(httpClient.delete).toHaveBeenCalledWith('/editions/ed-1/cfp/proposals/p-1/assignments/r-1')
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/cfp/assignments/auto', { body: { reviewers_per_proposal: 3 } })
    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/cfp/evaluation/export', { as: 'blob' })
  })
})
