import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { EditionSettingsError } from './editionSettingsError'

export interface EditionPayload {
  id: string
  name: string
  slug: string
  status: string
  status_label: string
  status_options: Array<{ status: string, label: string, unlocks: string[], blocks: string[] }>
  starts_on: string | null
  ends_on: string | null
  city: string | null
  state: string | null
  timezone: string
  site_mode: 'platform' | 'external'
  external_site_url: string | null
  site_url: string | null
  privacy_contact_email: string | null
  privacy_policy_url: string | null
  anonymize_after_event: boolean
}

export interface DomainPayload {
  id: string
  host: string
  is_platform: boolean
  is_primary: boolean
  verified: boolean
  verified_at: string | null
  verification: { txt: { name: string, value: string }, cname: { name: string, value: string } } | null
  last_checked_at: string | null
  last_check_error: string | null
  url: string
}

export interface AuditPayload {
  data: Array<{
    id: string
    action: string
    subject_type: string
    subject_id: string
    actor: { id: string, name: string | null } | null
    before: Record<string, unknown> | null
    after: Record<string, unknown> | null
    created_at: string
  }>
  meta: { current_page: number, last_page: number, total: number }
}

const editionUrl = (editionId: string) => `/editions/${editionId}`

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new EditionSettingsError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new EditionSettingsError('Request failed')
  }
}

export default {
  show: (editionId: string) => call(() => httpClient.get<{ data: EditionPayload }>(editionUrl(editionId))),
  update: (editionId: string, body: Record<string, unknown>) => call(() => httpClient.patch<{ data: EditionPayload }>(editionUrl(editionId), { body })),
  changeStatus: (editionId: string, status: string) => call(() => httpClient.post<{ data: EditionPayload }>(`${editionUrl(editionId)}/status`, { body: { status } })),
  domains: (editionId: string) => call(() => httpClient.get<{ data: DomainPayload[] }>(`${editionUrl(editionId)}/domains`)),
  addDomain: (editionId: string, host: string) => call(() => httpClient.post<{ data: DomainPayload }>(`${editionUrl(editionId)}/domains`, { body: { host } })),
  verifyDomain: (editionId: string, domainId: string) => call(() => httpClient.post<{ data: DomainPayload }>(`${editionUrl(editionId)}/domains/${domainId}/verify`)),
  makePrimary: (editionId: string, domainId: string) => call(() => httpClient.post<{ data: DomainPayload }>(`${editionUrl(editionId)}/domains/${domainId}/primary`)),
  removeDomain: (editionId: string, domainId: string) => call(() => httpClient.delete<null>(`${editionUrl(editionId)}/domains/${domainId}`)),
  audit: (editionId: string, query: { page: number, action?: string, actor_id?: string, from?: string, to?: string }) =>
    call(() => httpClient.get<AuditPayload>(`${editionUrl(editionId)}/audit`, { query })),
}
