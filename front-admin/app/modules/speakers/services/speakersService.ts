import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { SpeakersError } from './speakersError'

export interface SpeakerRowPayload {
  user_id: string
  name: string | null
  email: string | null
  state: string | null
  nordeste: boolean
  proposal: { id: string, number: number, title: string, track: string | null }
  status: string
  status_label: string
  deadline_at: string | null
  reminded_at: string | null
  attended_at: string | null
}

export interface SpeakersPayload {
  summary: { confirmed: number, awaiting: number, waitlisted: number, slots: number, nordeste_percent: number | null, nordeste_quota_percent: number }
  coverage: Array<{ state: string, confirmed: number }>
  data: SpeakerRowPayload[]
}

export interface PromotionsPayload {
  data: Array<{ track: { id: string | null, name: string, slots: number }, occupied: number, open_slots: number, candidates: Array<{ proposal_id: string, number: number, title: string, final_score: number | null }> }>
}

export interface LogisticsPayload {
  user_id: string
  origin_city: string | null
  origin_state: string | null
  needs_travel: boolean
  needs_lodging: boolean
  travel_paid_by_organization: boolean
  lodging_paid_by_organization: boolean
  organization_notes?: string | null
  restrictions?: { accessibility_needs: string | null, dietary_restrictions: string | null } | null
}

export interface SpeakerDetailPayload {
  data: {
    user_id: string
    name: string | null
    email: string | null
    can_view_private_data: boolean
    profile: { display_name: string, job_title: string | null, company: string | null, bio: string | null, city: string | null, state: string | null, links: Record<string, string | null>, photo_url: string | null } | null
    proposals: Array<{
      id: string, number: number, title: string, track: string | null, format_label: string | null, duration_minutes: number | null,
      decision: string | null, decision_label: string | null, status: string, status_label: string,
      deadline_at: string | null, responded_at: string | null, reminded_at: string | null, attended_at: string | null, reason: string | null
    }>
    logistics: LogisticsPayload | null
  }
}

const base = (editionId: string) => `/editions/${editionId}/cfp`

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new SpeakersError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new SpeakersError('Request failed')
  }
}

export default {
  list: (editionId: string) => call(() => httpClient.get<SpeakersPayload>(`${base(editionId)}/speakers`)),
  show: (editionId: string, userId: string) => call(() => httpClient.get<SpeakerDetailPayload>(`${base(editionId)}/speakers/${userId}`)),
  remind: (editionId: string, proposalId: string | null) =>
    call(() => httpClient.post<{ data: { reminded: number, skipped: number } }>(`${base(editionId)}/speakers/remind`, { body: proposalId ? { proposal_id: proposalId } : {} })),
  attendance: (editionId: string, userId: string, present: boolean) =>
    call(() => httpClient.post<{ data: { updated: number } }>(`${base(editionId)}/speakers/${userId}/attendance`, { body: { present } })),
  promotions: (editionId: string) => call(() => httpClient.get<PromotionsPayload>(`${base(editionId)}/promotions`)),
  promote: (editionId: string, proposalId: string) =>
    call(() => httpClient.post<{ data: { proposal_id: string } }>(`${base(editionId)}/promotions`, { body: { proposal_id: proposalId } })),
  updateLogistics: (editionId: string, userId: string, body: { travel_paid_by_organization: boolean, lodging_paid_by_organization: boolean, organization_notes: string | null }) =>
    call(() => httpClient.patch<{ data: LogisticsPayload }>(`${base(editionId)}/logistics/${userId}`, { body })),
  extendDeadline: (editionId: string, proposalId: string, deadlineAt: string) =>
    call(() => httpClient.post<{ data: { status: string, deadline_at: string } }>(`${base(editionId)}/confirmations/${proposalId}/extend`, { body: { deadline_at: deadlineAt } })),
}
