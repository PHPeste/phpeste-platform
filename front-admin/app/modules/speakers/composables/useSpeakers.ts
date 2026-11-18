import { computed, ref } from 'vue'
import speakersService, { type PromotionsPayload, type SpeakersPayload } from '../services/speakersService'
import { SpeakersError } from '../services/speakersError'
import locale from '../locales/pt-br'
import { initials } from './format'
import type { CoverageCell, PromotionGroup, SpeakerFilter, SpeakerRow, SpeakersSummary, SpeakerStatus } from '../types'

const STATUSES: SpeakerStatus[] = ['pending', 'confirmed', 'declined', 'withdrawn', 'no_response', 'cancelled', 'waitlisted']
const asStatus = (value: string): SpeakerStatus => STATUSES.find((status) => status === value) ?? 'pending'

export function translateRows(payload: SpeakersPayload['data']): SpeakerRow[] {
  return payload.map((row) => {
    const name = row.name ?? row.email ?? ''
    return {
      userId: row.user_id, name, email: row.email ?? '', initials: initials(name), state: row.state, nordeste: row.nordeste,
      proposal: row.proposal, status: asStatus(row.status), statusLabel: row.status_label, deadlineAt: row.deadline_at, remindedAt: row.reminded_at, attended: row.attended_at !== null,
    }
  })
}

function translatePromotions(payload: PromotionsPayload['data']): PromotionGroup[] {
  return payload.filter((group) => group.open_slots > 0).map((group) => {
    const next = group.candidates[0]
    return {
      track: group.track.name,
      openSlots: group.open_slots,
      next: next ? { proposalId: next.proposal_id, number: next.number, title: next.title, finalScore: next.final_score } : null,
    }
  })
}

export function messageFor(error: unknown): string {
  if (error instanceof SpeakersError && error.statusCode === 422 && error.fieldErrors) {
    return Object.values(error.fieldErrors).flat()[0] ?? locale.genericError
  }
  return locale.genericError
}

export function useSpeakers(editionId: string) {
  const rows = ref<SpeakerRow[]>([])
  const summary = ref<SpeakersSummary | null>(null)
  const coverage = ref<CoverageCell[]>([])
  const promotions = ref<PromotionGroup[]>([])
  const state = ref<'loading' | 'ready' | 'error'>('loading')
  const filter = ref<SpeakerFilter>('all')
  const search = ref('')

  async function refresh() {
    const [payload, promotionsPayload] = await Promise.all([speakersService.list(editionId), speakersService.promotions(editionId)])
    rows.value = translateRows(payload.data)
    coverage.value = payload.coverage
    promotions.value = translatePromotions(promotionsPayload.data)
    summary.value = {
      confirmed: payload.summary.confirmed,
      awaiting: payload.summary.awaiting,
      waitlisted: payload.summary.waitlisted,
      slots: payload.summary.slots,
      nordestePercent: payload.summary.nordeste_percent,
      nordesteQuotaPercent: payload.summary.nordeste_quota_percent,
    }
  }

  async function load(): Promise<void> {
    state.value = 'loading'
    try {
      await refresh()
      state.value = 'ready'
    } catch {
      state.value = 'error'
    }
  }

  const filtered = computed(() => {
    const term = search.value.trim().toLowerCase()
    return rows.value.filter((row) => {
      if (filter.value !== 'all' && row.status !== filter.value) return false
      return !term || `${row.name} ${row.email} ${row.proposal.title}`.toLowerCase().includes(term)
    })
  })

  const counts = computed<Record<SpeakerFilter, number>>(() => ({
    all: rows.value.length,
    confirmed: rows.value.filter((row) => row.status === 'confirmed').length,
    pending: rows.value.filter((row) => row.status === 'pending').length,
    waitlisted: rows.value.filter((row) => row.status === 'waitlisted').length,
  }))

  const missingStates = computed(() => coverage.value.filter((cell) => cell.confirmed === 0).map((cell) => cell.state))

  async function remindAll(): Promise<{ reminded: number, skipped: number } | string> {
    try {
      const { data } = await speakersService.remind(editionId, null)
      await refresh()
      return data
    } catch (error) {
      return messageFor(error)
    }
  }

  async function promote(proposalId: string): Promise<string | null> {
    try {
      await speakersService.promote(editionId, proposalId)
      await refresh()
      return null
    } catch (error) {
      return messageFor(error)
    }
  }

  return { rows, summary, coverage, promotions, state, filter, search, filtered, counts, missingStates, load, remindAll, promote }
}
