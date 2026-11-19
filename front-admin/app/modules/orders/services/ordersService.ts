import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { OrdersError } from './ordersError'

export interface Money { amount: number, currency: string }

export type OrderStatusValue = 'created' | 'awaiting_payment' | 'paid' | 'expired' | 'cancelled' | 'refunded'

export interface OrderItemPayload {
  id: string
  ticket_type_id: string
  ticket_type_name: string
  batch_name: string | null
  unit_price: Money
  discount: Money
  fee: Money
  total: Money
  attendee: { name: string, email: string }
  cancelled_at: string | null
  external_code: string | null
}

export interface OrderPayload {
  id: string
  number: number
  status: OrderStatusValue
  status_label: string
  source: string
  external: { platform: string | null, reference: string | null } | null
  buyer: { name: string, email: string, document: string | null }
  subtotal: Money
  discount: Money
  fee: Money
  total: Money
  refunded: Money
  gateway_fee: Money
  requires_payment: boolean
  coupon_code: string | null
  expires_at: string | null
  paid_at: string | null
  created_at: string
  items: OrderItemPayload[]
}

export interface PaymentPayload {
  method: string
  method_label: string
  status: string
  status_label: string
  installments: number
  expires_at: string | null
  paid_at: string | null
  failure_reason: string | null
}

export interface RefundPayload {
  id: string
  amount: Money
  status: 'unconfirmed' | 'pending' | 'completed' | 'failed'
  status_label: string
  reason: string
  reason_label: string
  note: string | null
  cancelled_item_ids: string[]
  failure_reason: string | null
  created_at: string
  completed_at: string | null
}

export interface HistoryPayload {
  id: string
  kind: string
  title: string
  detail: string | null
  actor: { id: string, name: string | null } | null
  at: string
}

export interface OrdersPage {
  data: OrderPayload[]
  meta: { current_page: number, last_page: number, total: number }
}

export interface ListQuery { page: number, status: string, q: string }

export interface RefundBody {
  amount_cents: number | null
  reason: string
  note: string | null
  cancel_tickets: boolean
  item_ids: string[] | null
}

const base = (editionId: string) => `/editions/${editionId}/ticketing/orders`

const filters = (query: Omit<ListQuery, 'page'>) => ({
  ...(query.status ? { status: query.status } : {}),
  ...(query.q.trim() ? { q: query.q.trim() } : {}),
})

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new OrdersError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new OrdersError('Request failed')
  }
}

export default {
  list: (editionId: string, query: ListQuery) => call(() => httpClient.get<OrdersPage>(base(editionId), { query: { page: query.page, ...filters(query) } })),
  show: (editionId: string, orderId: string) => call(() => httpClient.get<{ data: OrderPayload, meta: { payment: PaymentPayload | null } }>(`${base(editionId)}/${orderId}`)),
  history: (editionId: string, orderId: string) => call(() => httpClient.get<{ data: HistoryPayload[] }>(`${base(editionId)}/${orderId}/history`)),
  refunds: (editionId: string, orderId: string) => call(() => httpClient.get<{ data: RefundPayload[] }>(`${base(editionId)}/${orderId}/refunds`)),
  cancel: (editionId: string, orderId: string) => call(() => httpClient.post<{ data: OrderPayload }>(`${base(editionId)}/${orderId}/cancel`)),
  markPaid: (editionId: string, orderId: string, justification: string) =>
    call(() => httpClient.post<{ data: OrderPayload }>(`${base(editionId)}/${orderId}/mark-paid`, { body: { justification } })),
  remind: (editionId: string, orderId: string) => call(() => httpClient.post<{ data: { delivery_id: string } }>(`${base(editionId)}/${orderId}/payment-reminder`)),
  refund: (editionId: string, orderId: string, body: RefundBody) => call(() => httpClient.post<{ data: RefundPayload }>(`${base(editionId)}/${orderId}/refunds`, { body })),
  settleRefund: (editionId: string, orderId: string, refundId: string, completed: boolean) =>
    call(() => httpClient.post<{ data: RefundPayload }>(`${base(editionId)}/${orderId}/refunds/${refundId}/settle`, { body: { completed } })),
  exportCsv: (editionId: string, query: Omit<ListQuery, 'page'>) => call(() => httpClient.get<Blob>(`${base(editionId)}/export`, { query: filters(query), as: 'blob' })),
}
