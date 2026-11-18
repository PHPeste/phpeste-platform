import { computed, reactive, ref } from 'vue'
import cfpReviewService, { type EvaluationPayload, type ProposalPayload, type QueuePayload, type ReviewPayload } from '../services/cfpReviewService'
import { CfpReviewError } from '../services/cfpReviewError'
import locale from '../locales/pt-br'
import type { Criterion, EvaluationSummary, ItemState, MyReview, OtherReview, ProposalDetail, QueueFilter, ReviewItem, Reviewer } from '../types'

const MY_STATE: Record<string, ItemState> = { pending: 'pending', draft: 'draft', submitted: 'reviewed' }

const speakersOf = (payload: ProposalPayload): string[] | null =>
  payload.speakers_hidden || !payload.speakers ? null : payload.speakers.map((speaker) => speaker.name ?? speaker.email ?? '')

function baseItem(payload: ProposalPayload) {
  return { id: payload.id, number: payload.number, title: payload.title, track: payload.track?.name ?? null, nordeste: payload.nordeste, speakers: speakersOf(payload) }
}

function translateQueue(payload: QueuePayload): ReviewItem[] {
  return payload.data.map((item) => ({
    ...baseItem(item), state: MY_STATE[item.my_review_status] ?? 'pending', score: null, reviewsSubmitted: 0, reviewsAssigned: 0, criterionAverages: {},
  }))
}

function translateEvaluation(payload: EvaluationPayload): { items: ReviewItem[], summary: EvaluationSummary } {
  return {
    items: payload.data.map((item) => ({
      ...baseItem(item), state: item.review_status, score: item.final_score, reviewsSubmitted: item.reviews_submitted, reviewsAssigned: item.reviews_assigned, criterionAverages: item.criterion_averages,
    })),
    summary: {
      proposals: payload.summary.proposals,
      reviewed: payload.summary.reviewed,
      withoutReviewer: payload.summary.without_reviewer,
      averageScore: payload.summary.average_score,
      nordestePercent: payload.summary.nordeste_percent,
      nordesteQuotaPercent: payload.summary.nordeste_quota_percent,
    },
  }
}

function translateProposal(payload: ProposalPayload): ProposalDetail {
  return {
    ...baseItem(payload),
    format: payload.format_label,
    durationMinutes: payload.duration_minutes,
    level: payload.level_label,
    language: payload.language,
    summary: payload.summary ?? '',
    audience: payload.audience ?? '',
  }
}

const translateReview = (payload: ReviewPayload): MyReview => ({ status: payload.status, scores: payload.scores, comment: payload.comment ?? '', weightedScore: payload.weighted_score })

export const formatScore = (score: number | null) => (score === null ? '—' : score.toFixed(1).replace('.', ','))

function messageFor(error: unknown): string {
  if (error instanceof CfpReviewError) {
    if (error.statusCode === 422 && error.fieldErrors) return Object.values(error.fieldErrors).flat()[0] ?? locale.genericError
    if (error.statusCode === 403 && error.message) return error.message
  }
  return locale.genericError
}

export function useCfpReview(editionId: string, options: { organizer: boolean, userId: string | null }) {
  const items = ref<ReviewItem[]>([])
  const summary = ref<EvaluationSummary | null>(null)
  const reviewers = ref<Reviewer[]>([])
  const state = ref<'loading' | 'ready' | 'error'>('loading')
  const filter = ref<QueueFilter>('all')
  const search = ref('')

  const selectedId = ref<string | null>(null)
  const detailState = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')
  const proposal = ref<ProposalDetail | null>(null)
  const criteria = ref<Criterion[]>([])
  const myReview = ref<MyReview | null>(null)
  const others = ref<OtherReview[]>([])
  const othersLocked = ref(false)
  const assignments = ref<Array<{ id: string, name: string }>>([])
  const form = reactive({ scores: {} as Record<string, number>, comment: '' })
  const activeCriterion = ref(0)
  const busy = ref<'draft' | 'submit' | null>(null)
  const errorMessage = ref<string | null>(null)

  const filtered = computed(() => {
    const term = search.value.trim().toLowerCase()
    return items.value.filter((item) => {
      if (filter.value === 'pending' && !['pending', 'draft'].includes(item.state)) return false
      if (filter.value === 'reviewed' && !['reviewed', 'tie'].includes(item.state)) return false
      if (filter.value === 'tie' && item.state !== 'tie') return false
      if (!term) return true
      return `#${item.number} ${item.number} ${item.title} ${item.track ?? ''}`.toLowerCase().includes(term)
    })
  })

  const counts = computed(() => ({
    all: items.value.length,
    pending: items.value.filter((item) => ['pending', 'draft'].includes(item.state)).length,
    reviewed: items.value.filter((item) => ['reviewed', 'tie'].includes(item.state)).length,
    tie: items.value.filter((item) => item.state === 'tie').length,
  }))

  const isAssignedToMe = computed(() => !options.organizer || assignments.value.some((assignment) => assignment.id === options.userId))
  const canReview = computed(() => isAssignedToMe.value && myReview.value?.status !== 'submitted')
  const selected = computed(() => items.value.find((item) => item.id === selectedId.value) ?? null)
  const missingScores = computed(() => criteria.value.filter((criterion) => !form.scores[criterion.id]))

  async function refreshList() {
    if (options.organizer) {
      const translated = translateEvaluation(await cfpReviewService.evaluation(editionId))
      items.value = translated.items
      summary.value = translated.summary
    } else {
      items.value = translateQueue(await cfpReviewService.queue(editionId))
    }
  }

  async function refreshReviewers() {
    if (!options.organizer) return
    reviewers.value = (await cfpReviewService.reviewers(editionId)).data.map((reviewer) => ({ ...reviewer, name: reviewer.name ?? '' }))
  }

  async function loadOthers(proposalId: string) {
    others.value = []
    othersLocked.value = false
    try {
      others.value = (await cfpReviewService.otherReviews(editionId, proposalId)).data.map((review) => ({
        reviewer: review.reviewer, scores: review.scores, comment: review.comment ?? '', weightedScore: review.weighted_score,
      }))
    } catch (error) {
      if (error instanceof CfpReviewError && error.statusCode === 403) othersLocked.value = true
      else throw error
    }
  }

  async function loadAssignments(proposalId: string) {
    if (!options.organizer) return
    assignments.value = (await cfpReviewService.assignments(editionId, proposalId)).data.map((assignment) => ({ id: assignment.reviewer_id, name: assignment.name ?? '' }))
  }

  async function select(id: string): Promise<void> {
    selectedId.value = id
    detailState.value = 'loading'
    errorMessage.value = null

    try {
      const [{ data }] = await Promise.all([cfpReviewService.review(editionId, id), loadAssignments(id)])
      if (selectedId.value !== id) return
      proposal.value = translateProposal(data.proposal)
      criteria.value = data.criteria
      myReview.value = data.review ? translateReview(data.review) : null
      form.scores = { ...(myReview.value?.scores ?? {}) }
      form.comment = myReview.value?.comment ?? ''
      const firstMissing = criteria.value.findIndex((criterion) => !form.scores[criterion.id])
      activeCriterion.value = firstMissing === -1 ? 0 : firstMissing
      await (options.organizer || myReview.value?.status === 'submitted' ? loadOthers(id) : Promise.resolve())
      detailState.value = 'ready'
    } catch {
      if (selectedId.value === id) detailState.value = 'error'
    }
  }

  function firstPending(after: string | null = null): ReviewItem | null {
    const list = filtered.value.length ? filtered.value : items.value
    const start = after ? list.findIndex((item) => item.id === after) + 1 : 0
    const ordered = [...list.slice(start), ...list.slice(0, start)]
    return ordered.find((item) => item.id !== after && ['pending', 'draft'].includes(item.state)) ?? null
  }

  async function load(): Promise<void> {
    state.value = 'loading'
    try {
      await Promise.all([refreshList(), refreshReviewers()])
      state.value = 'ready'
      const first = firstPending() ?? items.value[0]
      if (first) await select(first.id)
    } catch {
      state.value = 'error'
    }
  }

  function move(direction: 1 | -1): Promise<void> | void {
    const list = filtered.value
    if (!list.length) return
    const index = list.findIndex((item) => item.id === selectedId.value)
    const next = list[(index + direction + list.length) % list.length]
    if (next && next.id !== selectedId.value) return select(next.id)
  }

  function setScore(criterionId: string, score: number) {
    form.scores = { ...form.scores, [criterionId]: score }
    const index = criteria.value.findIndex((criterion) => criterion.id === criterionId)
    if (index !== -1) activeCriterion.value = Math.min(index + 1, criteria.value.length - 1)
  }

  function scoreActive(score: number) {
    const criterion = criteria.value[activeCriterion.value]
    if (criterion && canReview.value) setScore(criterion.id, score)
  }

  async function save(submit: boolean): Promise<boolean> {
    const current = selected.value
    if (!current || !canReview.value) return false
    if (submit && missingScores.value.length) {
      errorMessage.value = locale.missingScores
      activeCriterion.value = criteria.value.findIndex((criterion) => criterion.id === missingScores.value[0]!.id)
      return false
    }

    busy.value = submit ? 'submit' : 'draft'
    errorMessage.value = null
    try {
      const { data } = await cfpReviewService.saveReview(editionId, current.id, { scores: form.scores, comment: form.comment.trim() || null, submit })
      myReview.value = translateReview(data)
      await refreshList()
      return true
    } catch (error) {
      errorMessage.value = messageFor(error)
      return false
    } finally {
      busy.value = null
    }
  }

  const saveDraft = () => save(false)

  async function submitAndNext(): Promise<boolean> {
    const current = selectedId.value
    if (!(await save(true))) return false
    const next = firstPending(current)
    await (next ? select(next.id) : current ? select(current) : Promise.resolve())
    return true
  }

  async function assign(reviewerId: string): Promise<string | null> {
    if (!selectedId.value) return null
    try {
      await cfpReviewService.assign(editionId, selectedId.value, reviewerId)
      await Promise.all([loadAssignments(selectedId.value), refreshList(), refreshReviewers()])
      return null
    } catch (error) {
      return messageFor(error)
    }
  }

  async function unassign(reviewerId: string): Promise<string | null> {
    if (!selectedId.value) return null
    try {
      await cfpReviewService.unassign(editionId, selectedId.value, reviewerId)
      await Promise.all([loadAssignments(selectedId.value), refreshList(), refreshReviewers()])
      return null
    } catch (error) {
      return messageFor(error)
    }
  }

  async function autoAssign(perProposal: number): Promise<{ created: number, unfilled: number[] } | string> {
    try {
      const { data } = await cfpReviewService.autoAssign(editionId, perProposal)
      await Promise.all([refreshList(), refreshReviewers(), selectedId.value ? loadAssignments(selectedId.value) : Promise.resolve()])
      return data
    } catch (error) {
      return messageFor(error)
    }
  }

  const exportCsv = () => cfpReviewService.exportCsv(editionId)

  return {
    items, summary, reviewers, state, filter, search, filtered, counts,
    selectedId, selected, detailState, proposal, criteria, myReview, others, othersLocked, assignments, form, activeCriterion, busy, errorMessage,
    isAssignedToMe, canReview, missingScores,
    load, select, move, setScore, scoreActive, saveDraft, submitAndNext, assign, unassign, autoAssign, exportCsv,
  }
}
