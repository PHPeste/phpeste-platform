import { httpClient } from '@/infrastructure/http/HttpClient'

const EDITIONS_URL = '/editions'

export interface EditionPayload {
  slug: string
  id: number
  name: string
  city: string
  state: string
  year: number
  site_url: string | null
  role: string
}

export default {
  /** Edições em que a pessoa logada tem algum papel. Devolve o payload cru. */
  async list(): Promise<{ data: EditionPayload[] }> {
    return httpClient.get<{ data: EditionPayload[] }>(EDITIONS_URL)
  },
}
