import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { CheckoutError } from './checkoutError'

export interface Money { amount: number, currency: string }

export interface QuestionPayload { id: string, label: string, type: 'text' | 'select' | 'boolean', required: boolean, options: string[] }

export interface TicketTypePayload {
  id: string
  name: string
  description: string | null
  min_per_order: number
  max_per_order: number
  pass_fee_to_buyer: boolean
  questions: QuestionPayload[]
  status: 'scheduled' | 'on_sale' | 'sold_out' | 'ended'
  status_label: string
  batch: { id: string, name: string, price: Money, ends_at: string | null, remaining: number | null } | null
  next_batch: { name: string, price: Money, opens_at: string | null } | null
}

export interface CatalogPayload { sold_out: boolean, unlocked: boolean, ticket_types: TicketTypePayload[] }

export interface QuotePayload {
  code: string
  subtotal: Money
  discount: Money
  total: Money
  requires_payment: boolean
  lines: Array<{ ticket_type_id: string, quantity: number, discount: Money, total: Money }>
}

export interface OrderPayload {
  id: string
  number: number
  status: 'created' | 'awaiting_payment' | 'paid' | 'expired' | 'cancelled' | 'refunded'
  status_label: string
  buyer: { name: string, email: string, document: string | null }
  subtotal: Money
  discount: Money
  fee: Money
  total: Money
  requires_payment: boolean
  coupon_code: string | null
  expires_at: string | null
  paid_at: string | null
  items: Array<{ id: string, ticket_type_id: string, ticket_type_name: string, batch_name: string | null, unit_price: Money, discount: Money, fee: Money, total: Money, attendee: { name: string, email: string } }>
  access_token?: string
}

export interface PlaceOrderBody {
  buyer: { name: string, email: string, document: string | null }
  coupon: string | null
  items: Array<{ ticket_type_id: string, attendees: Array<{ name: string, email: string, answers: Record<string, string | boolean> }> }>
}

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new CheckoutError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new CheckoutError('Request failed')
  }
}

const orders = (editionId: string) => `/checkout/editions/${editionId}/orders`

export default {
  catalog: (editionId: string) => call(() => httpClient.get<{ data: CatalogPayload }>(`/public/editions/${editionId}/tickets`)),
  checkCoupon: (editionId: string, code: string, items: Array<{ ticket_type_id: string, quantity: number }>) =>
    call(() => httpClient.post<{ data: QuotePayload }>(`/public/editions/${editionId}/coupons/check`, { body: { code, items } })),
  placeOrder: (editionId: string, body: PlaceOrderBody, idempotencyKey: string) =>
    call(() => httpClient.post<{ data: OrderPayload }>(orders(editionId), { body, headers: { 'Idempotency-Key': idempotencyKey } })),
  order: (editionId: string, orderId: string, token: string) =>
    call(() => httpClient.get<{ data: OrderPayload }>(`${orders(editionId)}/${orderId}`, { headers: { 'X-Order-Token': token } })),
  cancel: (editionId: string, orderId: string, token: string) =>
    call(() => httpClient.post<{ data: OrderPayload }>(`${orders(editionId)}/${orderId}/cancel`, { headers: { 'X-Order-Token': token } })),
}
