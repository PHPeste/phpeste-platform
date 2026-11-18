import { ref } from 'vue'
import speakersService, { type LogisticsPayload, type SpeakerDetailPayload } from '../services/speakersService'
import { SpeakersError } from '../services/speakersError'
import { initials } from './format'
import { messageFor } from './useSpeakers'
import type { SpeakerDetail, SpeakerLogistics, SpeakerStatus } from '../types'

const LINK_LABELS: Record<string, string> = { website: 'Site', github: 'GitHub', linkedin: 'LinkedIn', twitter: 'X', mastodon: 'Mastodon', bluesky: 'Bluesky', youtube: 'YouTube' }
const NORDESTE = ['AL', 'BA', 'CE', 'MA', 'PB', 'PE', 'PI', 'RN', 'SE']

function translateLogistics(payload: LogisticsPayload): SpeakerLogistics {
  return {
    originCity: payload.origin_city,
    originState: payload.origin_state,
    needsTravel: payload.needs_travel,
    needsLodging: payload.needs_lodging,
    travelPaidByOrganization: payload.travel_paid_by_organization,
    lodgingPaidByOrganization: payload.lodging_paid_by_organization,
    organizationNotes: payload.organization_notes ?? '',
    accessibilityNeeds: payload.restrictions?.accessibility_needs ?? null,
    dietaryRestrictions: payload.restrictions?.dietary_restrictions ?? null,
  }
}

function translate(payload: SpeakerDetailPayload['data']): SpeakerDetail {
  const name = payload.name ?? payload.email ?? ''
  const profile = payload.profile
  return {
    userId: payload.user_id,
    name,
    email: payload.email ?? '',
    initials: initials(name),
    displayName: profile?.display_name ?? name,
    jobTitle: profile?.job_title ?? null,
    company: profile?.company ?? null,
    bio: profile?.bio ?? null,
    city: profile?.city ?? null,
    state: profile?.state ?? null,
    nordeste: NORDESTE.includes(profile?.state ?? ''),
    links: Object.entries(profile?.links ?? {}).filter((entry): entry is [string, string] => !!entry[1]).map(([key, url]) => ({ label: LINK_LABELS[key] ?? key, url })),
    photoUrl: profile?.photo_url ?? null,
    canViewPrivateData: payload.can_view_private_data,
    talks: payload.proposals.map((talk) => ({
      id: talk.id, number: talk.number, title: talk.title, track: talk.track, invited: talk.origin === 'invited', format: talk.format_label, durationMinutes: talk.duration_minutes,
      decisionLabel: talk.decision_label, status: talk.status as SpeakerStatus, statusLabel: talk.status_label,
      deadlineAt: talk.deadline_at, respondedAt: talk.responded_at, remindedAt: talk.reminded_at, attendedAt: talk.attended_at, reason: talk.reason,
    })),
    logistics: payload.logistics ? translateLogistics(payload.logistics) : null,
  }
}

export function useSpeaker(editionId: string, userId: string) {
  const speaker = ref<SpeakerDetail | null>(null)
  const state = ref<'loading' | 'ready' | 'error' | 'not-found'>('loading')

  async function refresh() {
    speaker.value = translate((await speakersService.show(editionId, userId)).data)
  }

  async function load(): Promise<void> {
    state.value = 'loading'
    try {
      await refresh()
      state.value = 'ready'
    } catch (error) {
      state.value = error instanceof SpeakersError && error.statusCode === 404 ? 'not-found' : 'error'
    }
  }

  async function act<T>(request: () => Promise<T>): Promise<{ ok: true, data: T } | { ok: false, message: string }> {
    try {
      const data = await request()
      await refresh()
      return { ok: true, data }
    } catch (error) {
      return { ok: false, message: messageFor(error) }
    }
  }

  return {
    speaker, state, load,
    setAttendance: (present: boolean) => act(() => speakersService.attendance(editionId, userId, present)),
    remind: (proposalId: string) => act(async () => (await speakersService.remind(editionId, proposalId)).data),
    saveLogistics: (input: { travelPaidByOrganization: boolean, lodgingPaidByOrganization: boolean, organizationNotes: string }) => act(() => speakersService.updateLogistics(editionId, userId, {
      travel_paid_by_organization: input.travelPaidByOrganization,
      lodging_paid_by_organization: input.lodgingPaidByOrganization,
      organization_notes: input.organizationNotes.trim() || null,
    })),
    extendDeadline: (proposalId: string, deadlineAt: string) => act(() => speakersService.extendDeadline(editionId, proposalId, deadlineAt)),
  }
}
