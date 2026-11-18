import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import type { EditionPayload } from '@/modules/shell/services/editionService'
import { CommunitiesError } from './communitiesError'
import type { CloneInput, NewCommunityInput, NewEditionInput } from '../types'

const EDITIONS_URL = '/editions'
const COMMUNITIES_URL = '/communities'

export interface CommunityPayload {
  id: string
  name: string
  slug: string
  created_at: string
}

export interface CloneOptionsPayload {
  data: {
    parts: Array<{ id: string, label: string }>
    never_copied: string[]
  }
}

export interface ClonePayload {
  data: {
    id: string
    status: 'queued' | 'done' | 'failed'
    target_edition_id: string | null
    error: string | null
  }
}

function wrap(error: unknown): never {
  if (error instanceof HttpClientError) {
    throw new CommunitiesError(error.message, error.statusCode, error.fieldErrors)
  }
  throw new CommunitiesError('Request failed')
}

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    wrap(error)
  }
}

export default {
  listEditions: () => call(() => httpClient.get<{ data: EditionPayload[] }>(EDITIONS_URL)),

  listManagedCommunities: () => call(() => httpClient.get<{ data: CommunityPayload[] }>(COMMUNITIES_URL)),

  createCommunity: (input: NewCommunityInput) =>
    call(() => httpClient.post<{ data: CommunityPayload }>(COMMUNITIES_URL, { body: { name: input.name, slug: input.slug } })),

  createEdition: (communityId: string, input: NewEditionInput) =>
    call(() => httpClient.post<{ data: EditionPayload }>(`${COMMUNITIES_URL}/${communityId}/editions`, {
      body: {
        name: input.name,
        slug: input.slug,
        starts_on: input.startsOn || null,
        ends_on: input.endsOn || null,
        city: input.city || null,
        state: input.state || null,
      },
    })),

  cloneOptions: (sourceEditionId: string) =>
    call(() => httpClient.get<CloneOptionsPayload>(`${EDITIONS_URL}/${sourceEditionId}/clone-options`)),

  clone: (input: CloneInput) =>
    call(() => httpClient.post<ClonePayload>(`${EDITIONS_URL}/${input.sourceEditionId}/clone`, {
      body: { name: input.name, slug: input.slug, parts: input.parts },
    })),

  cloneStatus: (cloneId: string) => call(() => httpClient.get<ClonePayload>(`/edition-clones/${cloneId}`)),
}
