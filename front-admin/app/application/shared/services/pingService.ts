import { httpClient } from '@/infrastructure/http/HttpClient'
import type { operations } from '@/infrastructure/http/api-types'

const PING_URL = '/ping'

export type PingPayload = operations['ping']['responses'][200]['content']['application/json']

export default {
  async ping(): Promise<PingPayload> {
    return httpClient.get<PingPayload>(PING_URL)
  },
}
