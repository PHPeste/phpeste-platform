import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import authService from '@/application/shared/services/authService'
import { AccountError } from './accountError'

interface Money { amount: number, currency: string }

export interface TicketPayload {
  id: string
  code: string
  status: 'active' | 'cancelled'
  status_label: string
  order_id: string
  order_number: number | null
  ticket_type_name: string | null
  batch_name: string | null
  holder: { name: string, email: string, badge_name: string | null, company: string | null, tshirt_size: string | null, accessibility?: string | null }
  missing: string[]
  issued_at: string | null
  cancelled_at: string | null
  qr?: string
  transfers?: Array<{ from: { name: string, email: string }, to: { name: string, email: string }, channel: string, at: string | null }>
}

export interface OrderPayload {
  id: string
  number: number
  status: string
  status_label: string
  subtotal: Money
  discount: Money
  fee: Money
  total: Money
  refunded: Money
  coupon_code: string | null
  created_at: string | null
  paid_at: string | null
  expires_at: string | null
  items: Array<{ id: string, ticket_type_name: string, batch_name: string | null, attendee_name: string, total: Money, cancelled_at: string | null }>
  refunds: Array<{ id: string, amount: Money, status: string, status_label: string, reason_label: string, created_at: string | null, completed_at: string | null }>
}

export interface TicketBody {
  holder_name?: string
  badge_name?: string | null
  company?: string | null
  tshirt_size?: string | null
  accessibility?: string | null
}

interface Listing<T> { data: T[], meta: { email_verified: boolean } }

const ticketsUrl = (editionId: string) => `/attendee/editions/${editionId}/tickets`

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new AccountError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new AccountError('Request failed')
  }
}

export default {
  tickets: (editionId: string) => call(() => httpClient.get<Listing<TicketPayload>>(ticketsUrl(editionId))),
  ticket: (editionId: string, id: string) => call(() => httpClient.get<{ data: TicketPayload }>(`${ticketsUrl(editionId)}/${id}`)),
  update: (editionId: string, id: string, body: TicketBody) => call(() => httpClient.patch<{ data: TicketPayload }>(`${ticketsUrl(editionId)}/${id}`, { body })),
  transfer: (editionId: string, id: string, body: { name: string, email: string }) => call(() => httpClient.post<{ data: TicketPayload }>(`${ticketsUrl(editionId)}/${id}/transfer`, { body })),
  resend: (editionId: string, id: string) => call(() => httpClient.post<{ data: TicketPayload }>(`${ticketsUrl(editionId)}/${id}/resend`)),
  orders: (editionId: string) => call(() => httpClient.get<Listing<OrderPayload>>(`/attendee/editions/${editionId}/orders`)),
  resendVerification: () => call(() => authService.resendVerification()),
}
