import { httpClient } from '@/infrastructure/http/HttpClient'

const EDITIONS_URL = '/editions'

export interface EditionPayload {
  id: string
  slug: string
  name: string
  community_id: string
  community?: { id: string, name: string, slug: string }
  status: string
  status_label: string
  starts_on: string | null
  ends_on: string | null
  city: string | null
  state: string | null
  year: number | null
  site_url: string | null
  role: string | null
}

export default {
  /** Edições em que a pessoa logada tem algum papel. Devolve o payload cru. */
  async list(): Promise<{ data: EditionPayload[] }> {
    return httpClient.get<{ data: EditionPayload[] }>(EDITIONS_URL)
  },
}
