import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { ProposalsError } from './proposalsError'

export interface PublicCfpPayload {
  data: {
    status: 'draft' | 'scheduled' | 'open' | 'closed'
    status_label: string
    opens_at: string | null
    closes_at: string | null
    anonymous_review: boolean
    proposals_per_person: number
    tracks: Array<{ id: string, name: string }>
    form: {
      formats: Array<{ id: string, label: string }>
      levels: Array<{ id: string, label: string }>
      languages: Array<{ id: string, label: string }>
      limits: { title: number, summary: number, audience: number }
    }
  }
}

export interface SpeakerProfilePayload {
  data: {
    public: { display_name: string, job_title: string | null, company: string | null, bio: string | null, city: string | null, state: string | null, links: Record<string, string | null> } | null
    private: { accessibility_needs: string | null, dietary_restrictions: string | null }
    email: string
  }
}

export interface ProposalPayload {
  id: string
  number: number
  title: string
  track: { id: string, name: string } | null
  summary: string | null
  audience: string | null
  format: string | null
  format_label: string | null
  level: string | null
  level_label: string | null
  language: string
  status: 'draft' | 'submitted' | 'withdrawn'
  status_label: string
  decision: string | null
  decision_label: string | null
  missing_for_submission: string[]
  speakers?: Array<{ user_id: string, is_owner: boolean }>
  confirmation?: { status: string, status_label: string, deadline_at: string, responded_at: string | null } | null
}

export interface ProposalBody {
  title?: string
  track_id?: string | null
  format?: string | null
  level?: string | null
  language?: string
  summary?: string | null
  audience?: string | null
}

export interface ProfileBody {
  display_name: string
  job_title: string | null
  company: string | null
  bio: string | null
  city: string | null
  state: string | null
  links: { github: string | null, linkedin: string | null, website: string | null }
  accessibility_needs: string | null
  dietary_restrictions: string | null
}

const speakerUrl = (editionId: string) => `/speaker/editions/${editionId}/proposals`

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new ProposalsError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new ProposalsError('Request failed')
  }
}

export default {
  cfp: (editionId: string) => call(() => httpClient.get<PublicCfpPayload>(`/public/editions/${editionId}/cfp`)),
  profile: () => call(() => httpClient.get<SpeakerProfilePayload>('/me/speaker-profile')),
  saveProfile: (body: ProfileBody) => call(() => httpClient.put<SpeakerProfilePayload>('/me/speaker-profile', { body })),
  list: (editionId: string) => call(() => httpClient.get<{ data: ProposalPayload[] }>(speakerUrl(editionId))),
  show: (editionId: string, id: string) => call(() => httpClient.get<{ data: ProposalPayload }>(`${speakerUrl(editionId)}/${id}`)),
  create: (editionId: string, body: ProposalBody) => call(() => httpClient.post<{ data: ProposalPayload }>(speakerUrl(editionId), { body })),
  update: (editionId: string, id: string, body: ProposalBody) => call(() => httpClient.patch<{ data: ProposalPayload }>(`${speakerUrl(editionId)}/${id}`, { body })),
  submit: (editionId: string, id: string) => call(() => httpClient.post<{ data: ProposalPayload }>(`${speakerUrl(editionId)}/${id}/submit`)),
  withdraw: (editionId: string, id: string) => call(() => httpClient.post<{ data: ProposalPayload }>(`${speakerUrl(editionId)}/${id}/withdraw`)),
  confirm: (editionId: string, id: string) => call(() => httpClient.post<{ data: { status: string } }>(`${speakerUrl(editionId)}/${id}/confirm`, { body: {} })),
  decline: (editionId: string, id: string, reason: string | null) => call(() => httpClient.post<{ data: { status: string } }>(`${speakerUrl(editionId)}/${id}/decline`, { body: { reason } })),
}
