import { beforeEach, describe, expect, it, vi } from 'vitest'
import cfpReviewService from '../services/cfpReviewService'
import { CfpReviewError } from '../services/cfpReviewError'
import { formatScore, useCfpReview } from './useCfpReview'

vi.mock('../services/cfpReviewService', () => ({
  default: { queue: vi.fn(), evaluation: vi.fn(), review: vi.fn(), saveReview: vi.fn(), otherReviews: vi.fn(), reviewers: vi.fn(), assignments: vi.fn(), assign: vi.fn(), unassign: vi.fn(), autoAssign: vi.fn(), exportCsv: vi.fn() },
}))

const proposal = (number: number, extra: Record<string, unknown> = {}) => ({
  id: `p-${number}`, number, title: `Proposta ${number}`, track: { id: 't', name: 'Backend' }, summary: 'Resumo', audience: 'Público',
  format: 'talk', format_label: 'Palestra de 30 min', duration_minutes: 30, level: 'beginner', level_label: 'Iniciante', language: 'pt_BR',
  nordeste: true, speakers_hidden: true, ...extra,
})
const criteria = [{ id: 'c-1', name: 'Relevância', description: null, weight: 3 }, { id: 'c-2', name: 'Clareza', description: null, weight: 1 }]
const queue = (statuses: Array<'pending' | 'draft' | 'submitted'>) => ({ data: statuses.map((status, index) => ({ ...proposal(index + 1), my_review_status: status })) })

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(cfpReviewService.review).mockImplementation(async (_edition, id) => ({ data: { proposal: proposal(Number(id.slice(2))), criteria, review: null } }))
})

describe('useCfpReview para quem revisa', () => {
  it('abre a primeira pendente sem identificar o autor', async () => {
    vi.mocked(cfpReviewService.queue).mockResolvedValue(queue(['submitted', 'pending', 'pending']))
    const review = useCfpReview('ed-1', { organizer: false, userId: 'u-1' })
    await review.load()

    expect(review.selectedId.value).toBe('p-2')
    expect(review.proposal.value?.speakers).toBeNull()
    expect(review.items.value.map((item) => item.state)).toEqual(['reviewed', 'pending', 'pending'])
    expect(cfpReviewService.evaluation).not.toHaveBeenCalled()
    expect(cfpReviewService.otherReviews).not.toHaveBeenCalled()
  })

  it('a nota pelo teclado vai para o critério em destaque e passa para o próximo', async () => {
    vi.mocked(cfpReviewService.queue).mockResolvedValue(queue(['pending']))
    const review = useCfpReview('ed-1', { organizer: false, userId: 'u-1' })
    await review.load()

    review.scoreActive(4)
    expect(review.form.scores).toEqual({ 'c-1': 4 })
    expect(review.activeCriterion.value).toBe(1)
    review.scoreActive(5)
    expect(review.form.scores).toEqual({ 'c-1': 4, 'c-2': 5 })
  })

  it('não envia sem todas as notas e destaca o critério que falta', async () => {
    vi.mocked(cfpReviewService.queue).mockResolvedValue(queue(['pending']))
    const review = useCfpReview('ed-1', { organizer: false, userId: 'u-1' })
    await review.load()
    review.setScore('c-2', 3)

    expect(await review.submitAndNext()).toBe(false)
    expect(review.errorMessage.value).toBe('Dê nota para todos os critérios antes de enviar.')
    expect(review.activeCriterion.value).toBe(0)
    expect(cfpReviewService.saveReview).not.toHaveBeenCalled()
  })

  it('enviar a nota abre a próxima pendente', async () => {
    vi.mocked(cfpReviewService.queue).mockResolvedValueOnce(queue(['pending', 'submitted', 'pending'])).mockResolvedValueOnce(queue(['submitted', 'submitted', 'pending']))
    vi.mocked(cfpReviewService.saveReview).mockResolvedValueOnce({ data: { status: 'submitted', scores: { 'c-1': 4, 'c-2': 5 }, comment: null, weighted_score: 4.25, submitted_at: '2027-03-10T12:00:00Z' } })
    const review = useCfpReview('ed-1', { organizer: false, userId: 'u-1' })
    await review.load()
    review.scoreActive(4)
    review.scoreActive(5)

    expect(await review.submitAndNext()).toBe(true)
    expect(cfpReviewService.saveReview).toHaveBeenCalledWith('ed-1', 'p-1', { scores: { 'c-1': 4, 'c-2': 5 }, comment: null, submit: true })
    expect(review.selectedId.value).toBe('p-3')
  })

  it('filtra e busca na fila', async () => {
    vi.mocked(cfpReviewService.queue).mockResolvedValue(queue(['pending', 'draft', 'submitted']))
    const review = useCfpReview('ed-1', { organizer: false, userId: 'u-1' })
    await review.load()

    review.filter.value = 'pending'
    expect(review.filtered.value.map((item) => item.number)).toEqual([1, 2])
    review.filter.value = 'all'
    review.search.value = '#3'
    expect(review.filtered.value.map((item) => item.number)).toEqual([3])
    expect(review.counts.value).toEqual({ all: 3, pending: 2, reviewed: 1, tie: 0 })
  })

  it('mostra que as outras notas só aparecem depois de enviar', async () => {
    vi.mocked(cfpReviewService.queue).mockResolvedValue(queue(['submitted']))
    vi.mocked(cfpReviewService.review).mockResolvedValueOnce({ data: { proposal: proposal(1), criteria, review: { status: 'submitted', scores: { 'c-1': 4, 'c-2': 4 }, comment: null, weighted_score: 4, submitted_at: null } } })
    vi.mocked(cfpReviewService.otherReviews).mockRejectedValueOnce(new CfpReviewError('Envie a sua avaliação antes.', 403))
    const review = useCfpReview('ed-1', { organizer: false, userId: 'u-1' })
    await review.load()

    expect(review.canReview.value).toBe(false)
    expect(review.othersLocked.value).toBe(true)
  })
})

describe('useCfpReview para a organização', () => {
  const evaluation = {
    summary: { proposals: 2, reviewed: 1, without_reviewer: 1, average_score: 4.1, nordeste_percent: 50, nordeste_quota_percent: 50 },
    criteria,
    data: [
      { ...proposal(31, { speakers_hidden: false, speakers: [{ user_id: 'a', name: 'Ana Lima', email: 'a@x.dev', state: 'PB', is_owner: true }] }), final_score: 4.1, review_status: 'tie', reviews_assigned: 3, reviews_submitted: 3, criterion_averages: { 'c-1': 4.33 } },
      { ...proposal(32), final_score: null, review_status: 'pending', reviews_assigned: 0, reviews_submitted: 0, criterion_averages: {} },
    ],
  }

  beforeEach(() => {
    vi.mocked(cfpReviewService.evaluation).mockResolvedValue(evaluation as never)
    vi.mocked(cfpReviewService.reviewers).mockResolvedValue({ data: [{ id: 'r-1', name: 'Revisor A', assigned: 2, submitted: 1 }] })
    vi.mocked(cfpReviewService.assignments).mockResolvedValue({ data: [] })
    vi.mocked(cfpReviewService.otherReviews).mockResolvedValue({ data: [] })
  })

  it('carrega o quadro com nota agregada, empate e resumo, e só deixa avaliar o que foi atribuído', async () => {
    const review = useCfpReview('ed-1', { organizer: true, userId: 'u-org' })
    await review.load()

    expect(review.summary.value).toMatchObject({ proposals: 2, withoutReviewer: 1, averageScore: 4.1 })
    expect(review.items.value[0]).toMatchObject({ state: 'tie', score: 4.1, reviewsSubmitted: 3, speakers: ['Ana Lima'] })
    expect(review.selectedId.value).toBe('p-32')
    expect(review.canReview.value).toBe(false)
    expect(review.counts.value.tie).toBe(1)
  })

  it('atribui revisor e recarrega atribuições, quadro e carga', async () => {
    vi.mocked(cfpReviewService.assign).mockResolvedValueOnce({ data: { reviewer_id: 'r-1' } })
    const review = useCfpReview('ed-1', { organizer: true, userId: 'u-org' })
    await review.load()

    expect(await review.assign('r-1')).toBeNull()
    expect(cfpReviewService.assign).toHaveBeenCalledWith('ed-1', 'p-32', 'r-1')
    expect(cfpReviewService.evaluation).toHaveBeenCalledTimes(2)
    expect(cfpReviewService.reviewers).toHaveBeenCalledTimes(2)
  })

  it('devolve a recusa de conflito de interesse do backend', async () => {
    vi.mocked(cfpReviewService.assign).mockRejectedValueOnce(new CfpReviewError('x', 422, { reviewer_id: ['Essa pessoa é autora da proposta.'] }))
    const review = useCfpReview('ed-1', { organizer: true, userId: 'u-org' })
    await review.load()

    expect(await review.assign('r-1')).toBe('Essa pessoa é autora da proposta.')
  })
})

it('formata nota com vírgula', () => {
  expect(formatScore(4.125)).toBe('4,1')
  expect(formatScore(null)).toBe('—')
})
