import { computed, ref } from 'vue'
import attendeesService, { type FilterValue, type SummaryPayload, type TicketPayload } from '../services/attendeesService'
import { AttendeesError } from '../services/attendeesError'
import locale from '../locales/pt-br'
import type { Attendee, AttendeeDetail, AttendeeInput, AttendeesSummary, MutationResult, ReminderPreview } from '../types'

export const translateAttendee = (payload: TicketPayload): Attendee => ({
  id: payload.id,
  code: payload.code,
  active: payload.status === 'active',
  statusLabel: payload.status_label,
  orderNumber: payload.order_number,
  ticketTypeName: payload.ticket_type_name ?? '',
  name: payload.holder.name,
  email: payload.holder.email,
  badgeName: payload.holder.badge_name,
  company: payload.holder.company,
  tshirtSize: payload.holder.tshirt_size,
  accessibility: 'accessibility' in payload.holder ? (payload.holder.accessibility ?? null) : undefined,
  missing: payload.missing,
  transferredFrom: payload.transferred_from,
  lastSentAt: payload.last_sent_at,
})

export const translateSummary = (payload: SummaryPayload): AttendeesSummary => ({ ...payload })

const translateDetail = (payload: TicketPayload): AttendeeDetail => ({
  attendee: translateAttendee(payload),
  transfers: (payload.transfers ?? []).map((transfer) => ({
    fromName: transfer.from.name,
    toName: transfer.to.name,
    byOrganizer: transfer.channel === 'organizer',
    at: transfer.at,
  })),
  edits: (payload.edits ?? []).map((edit) => ({
    id: edit.id,
    changes: edit.changes,
    actorName: edit.actor ? (edit.actor.name ?? '') : null,
    at: edit.at,
  })).reverse(),
})

const FIELDS: Record<string, string> = { holder_name: 'name', badge_name: 'badgeName', company: 'company', tshirt_size: 'tshirtSize', accessibility: 'accessibility', confirm: 'recipients', recipients: 'recipients' }

function failure(error: unknown): { ok: false, message: string, fieldErrors: Record<string, string> } {
  if (error instanceof AttendeesError && error.statusCode === 422 && error.fieldErrors) {
    const fieldErrors = Object.fromEntries(Object.entries(error.fieldErrors).map(([key, messages]) => [FIELDS[key] ?? key, messages[0] ?? '']))
    return { ok: false, message: Object.values(fieldErrors)[0] ?? locale.genericError, fieldErrors }
  }
  return { ok: false, message: locale.genericError, fieldErrors: {} }
}

const orNull = (value: string) => (value.trim() === '' ? null : value.trim())

export function useAttendees(editionId: string, options: { private: boolean }) {
  const attendees = ref<Attendee[]>([])
  const summary = ref<AttendeesSummary | null>(null)
  const page = ref(1)
  const pageCount = ref(1)
  const total = ref(0)
  const filter = ref<FilterValue>('')
  const query = ref('')
  const state = ref<'loading' | 'ready' | 'error'>('loading')
  const filtered = computed(() => filter.value !== '' || query.value.trim() !== '')

  const detail = ref<AttendeeDetail | null>(null)
  const detailState = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')

  async function load() {
    state.value = 'loading'
    try {
      const response = await attendeesService.list(editionId, { page: page.value, filter: filter.value, q: query.value })
      attendees.value = response.data.map(translateAttendee)
      pageCount.value = Math.max(1, response.meta.last_page)
      total.value = response.meta.total
      summary.value = response.meta.summary ? translateSummary(response.meta.summary) : null
      state.value = 'ready'
    } catch {
      state.value = 'error'
    }
  }

  async function search(nextFilter: FilterValue, nextQuery: string) {
    filter.value = nextFilter
    query.value = nextQuery
    page.value = 1
    await load()
  }

  async function goTo(next: number) {
    page.value = next
    await load()
  }

  function replace(payload: TicketPayload) {
    detail.value = translateDetail(payload)
    attendees.value = attendees.value.map((item) => (item.id === payload.id ? { ...detail.value!.attendee, transferredFrom: item.transferredFrom } : item))
  }

  async function open(ticketId: string) {
    detailState.value = 'loading'
    try {
      replace((await attendeesService.show(editionId, ticketId)).data)
      detailState.value = 'ready'
    } catch {
      detailState.value = 'error'
    }
  }

  function close() {
    detail.value = null
    detailState.value = 'idle'
  }

  async function save(input: AttendeeInput): Promise<MutationResult> {
    const ticketId = detail.value?.attendee.id
    if (!ticketId) return { ok: false, message: locale.genericError, fieldErrors: {} }
    try {
      const response = await attendeesService.update(editionId, ticketId, {
        holder_name: input.name.trim(),
        badge_name: orNull(input.badgeName),
        company: orNull(input.company),
        tshirt_size: orNull(input.tshirtSize),
        ...(options.private ? { accessibility: orNull(input.accessibility) } : {}),
      })
      replace(response.data)
      void refreshSummary()
      return { ok: true }
    } catch (error) {
      return failure(error)
    }
  }

  async function resend(): Promise<MutationResult> {
    const ticketId = detail.value?.attendee.id
    if (!ticketId) return { ok: false, message: locale.genericError, fieldErrors: {} }
    try {
      await attendeesService.resend(editionId, ticketId)
      await open(ticketId)
      return { ok: true }
    } catch (error) {
      return failure(error)
    }
  }

  async function refreshSummary() {
    try {
      const response = await attendeesService.list(editionId, { page: page.value, filter: filter.value, q: query.value })
      summary.value = response.meta.summary ? translateSummary(response.meta.summary) : summary.value
    } catch {
      // O resumo antigo continua na tela; a próxima carga corrige.
    }
  }

  async function reminderPreview(): Promise<ReminderPreview | null> {
    try {
      const { data } = await attendeesService.reminderPreview(editionId)
      return { recipients: data.recipients, subject: data.email?.subject ?? null, html: data.email?.html ?? null }
    } catch {
      return null
    }
  }

  async function remind(recipients: number): Promise<MutationResult> {
    try {
      await attendeesService.remind(editionId, recipients)
      return { ok: true }
    } catch (error) {
      return failure(error)
    }
  }

  async function badges(): Promise<Blob | null> {
    try {
      return await attendeesService.badges(editionId)
    } catch {
      return null
    }
  }

  return { attendees, summary, page, pageCount, total, filter, query, state, filtered, detail, detailState, load, search, goTo, open, close, save, resend, reminderPreview, remind, badges }
}
