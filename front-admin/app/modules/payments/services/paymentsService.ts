import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { PaymentsError } from './paymentsError'

export interface DriverPayload { key: string, label: string, needs_public_key: boolean }

export interface AccountPayload {
  id: string
  driver: string
  environment: 'sandbox' | 'production'
  environment_label: string
  credentials_hint: string
  holder: { type: 'individual' | 'company', type_label: string, name: string, document: string }
  methods: { key: string, label: string }[]
  max_installments: number
  pix_expiration_minutes: number
  active: boolean
  ready: boolean
  verified_at: string | null
  webhook_verified_at: string | null
  webhook_rejections: { count: number, last_at: string | null }
  retired_at: string | null
  created_at: string
  webhook_url: string | null
  last_event_at: string | null
}

export interface AccountsPayload {
  active: AccountPayload | null
  previous: AccountPayload[]
  drivers: DriverPayload[]
}

export interface WebhookEventPayload {
  id: string
  payment_account_id: string
  driver: string
  event_id: string
  type: string
  gateway_charge_id: string | null
  status: 'received' | 'processing' | 'processed' | 'ignored' | 'failed'
  status_label: string
  result: string | null
  attempts: number
  can_reprocess: boolean
  next_attempt_at: string | null
  processed_at: string | null
  received_at: string
}

export interface EventsPage {
  data: WebhookEventPayload[]
  meta: { current_page: number, last_page: number, total: number }
}

export interface CreateAccountBody {
  driver: string
  environment: string
  api_key: string
  webhook_secret: string
  public_key?: string
  holder_type: string
  holder_name: string
  holder_document: string
  methods: string[]
  max_installments: number
  pix_expiration_minutes: number
  confirm: boolean
}

export interface UpdateAccountBody {
  holder_name: string
  methods: string[]
  max_installments: number
  pix_expiration_minutes: number
}

const base = (editionId: string) => `/editions/${editionId}/payments`

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new PaymentsError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new PaymentsError('Request failed')
  }
}

export default {
  accounts: (editionId: string) => call(() => httpClient.get<{ data: AccountsPayload }>(`${base(editionId)}/accounts`)),
  create: (editionId: string, body: CreateAccountBody) => call(() => httpClient.post<{ data: AccountPayload }>(`${base(editionId)}/accounts`, { body })),
  update: (editionId: string, accountId: string, body: UpdateAccountBody) =>
    call(() => httpClient.patch<{ data: AccountPayload }>(`${base(editionId)}/accounts/${accountId}`, { body })),
  verify: (editionId: string, accountId: string) => call(() => httpClient.post<{ data: AccountPayload }>(`${base(editionId)}/accounts/${accountId}/verify`)),
  events: (editionId: string, page: number, status: string) =>
    call(() => httpClient.get<EventsPage>(`${base(editionId)}/webhook-events`, { query: { page, ...(status ? { status } : {}) } })),
  reprocess: (editionId: string, eventId: string) => call(() => httpClient.post<{ data: WebhookEventPayload }>(`${base(editionId)}/webhook-events/${eventId}/reprocess`)),
}
