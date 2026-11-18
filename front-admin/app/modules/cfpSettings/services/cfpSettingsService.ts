import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { CfpSettingsError } from './cfpSettingsError'

export interface SettingsPayload {
  data: {
    status: string
    status_label: string
    days_until_close: number | null
    opens_at: string | null
    closes_at: string | null
    results_at: string | null
    anonymous_review: boolean
    require_nordeste_quota: boolean
    nordeste_quota_percent: number
    proposals_per_person: number
  }
  meta: {
    form: {
      formats: Array<{ id: string, label: string, minutes: number }>
      levels: Array<{ id: string, label: string }>
      languages: Array<{ id: string, label: string }>
      limits: { title: number, summary: number, audience: number }
    }
  }
}

export interface SettingsBody {
  opens_at: string | null
  closes_at: string | null
  results_at: string | null
  anonymous_review: boolean
  require_nordeste_quota: boolean
  nordeste_quota_percent: number
  proposals_per_person: number
}

export interface TrackPayload {
  id: string
  name: string
  slots: number
  position: number
  proposals_count: number
}

export interface CriterionPayload {
  id: string
  name: string
  description: string | null
  weight: number
  position: number
}

export interface CriteriaPayload {
  data: CriterionPayload[]
  meta: { submitted_reviews: number }
}

const base = (editionId: string) => `/editions/${editionId}/cfp`

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new CfpSettingsError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new CfpSettingsError('Request failed')
  }
}

export default {
  settings: (editionId: string) => call(() => httpClient.get<SettingsPayload>(`${base(editionId)}/settings`)),
  updateSettings: (editionId: string, body: SettingsBody) =>
    call(() => httpClient.patch<SettingsPayload>(`${base(editionId)}/settings`, { body })),
  tracks: (editionId: string) => call(() => httpClient.get<{ data: TrackPayload[] }>(`${base(editionId)}/tracks`)),
  createTrack: (editionId: string, body: { name: string, slots: number }) =>
    call(() => httpClient.post<{ data: TrackPayload }>(`${base(editionId)}/tracks`, { body })),
  updateTrack: (editionId: string, trackId: string, body: { name?: string, slots?: number, position?: number }) =>
    call(() => httpClient.patch<{ data: TrackPayload }>(`${base(editionId)}/tracks/${trackId}`, { body })),
  deleteTrack: (editionId: string, trackId: string) =>
    call(() => httpClient.delete<null>(`${base(editionId)}/tracks/${trackId}`)),
  criteria: (editionId: string) => call(() => httpClient.get<CriteriaPayload>(`${base(editionId)}/criteria`)),
  createCriterion: (editionId: string, body: { name: string, description: string | null, weight: number, confirm: boolean }) =>
    call(() => httpClient.post<{ data: CriterionPayload }>(`${base(editionId)}/criteria`, { body })),
  updateCriterion: (editionId: string, criterionId: string, body: { name?: string, description?: string | null, weight?: number, position?: number, confirm: boolean }) =>
    call(() => httpClient.patch<{ data: CriterionPayload }>(`${base(editionId)}/criteria/${criterionId}`, { body })),
  deleteCriterion: (editionId: string, criterionId: string, confirm: boolean) =>
    call(() => httpClient.delete<null>(`${base(editionId)}/criteria/${criterionId}`, { body: { confirm } })),
}
