import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { TicketsError } from './ticketsError'

export interface Money { amount: number, currency: string }

export interface BatchPayload {
  id: string
  ticket_type_id: string
  name: string
  position: number
  price: Money
  quantity: number
  allocated: number
  remaining: number
  starts_at: string | null
  ends_at: string | null
  on_sell_out: 'next' | 'close'
  status: 'scheduled' | 'on_sale' | 'sold_out' | 'ended'
  status_label: string
}

export interface QuestionPayload { label: string, type: 'text' | 'select' | 'boolean', required?: boolean, options?: string[] }

export interface TicketTypePayload {
  id: string
  name: string
  description: string | null
  visibility: 'public' | 'code' | 'hidden'
  visibility_label: string
  access_code: string | null
  min_per_order: number
  max_per_order: number
  show_remaining: boolean
  pass_fee_to_buyer: boolean
  questions: QuestionPayload[]
  position: number
  allocated: number
  quantity: number
  current_batch_id: string | null
  batches: BatchPayload[]
}

export interface SettingsPayload {
  gateway_ready: boolean
  capacity: number | null
  allocated: number
  remaining: number | null
  reservation_minutes: number
  max_tickets_per_buyer: number | null
  gateway_fee_basis_points: number
}

export interface CouponPayload {
  id: string
  code: string
  description: string | null
  kind: 'percent' | 'fixed'
  kind_label: string
  value: number
  max_uses: number | null
  used: number
  remaining: number | null
  starts_at: string | null
  ends_at: string | null
  ticket_type_ids: string[]
  active: boolean
  status: string
  discount_total: Money
}

export interface TicketTypeBody {
  name: string
  description: string | null
  visibility: string
  access_code: string | null
  min_per_order: number
  max_per_order: number
  show_remaining: boolean
  pass_fee_to_buyer: boolean
  questions: QuestionPayload[]
}

export interface BatchBody {
  name: string
  price_cents: number
  quantity: number
  starts_at: string | null
  ends_at: string | null
  on_sell_out: string
}

export interface CouponBody {
  code: string
  description: string | null
  kind: string
  value: number
  max_uses: number | null
  starts_at: string | null
  ends_at: string | null
  ticket_type_ids: string[]
  active: boolean
}

export interface SettingsBody {
  capacity: number | null
  reservation_minutes: number
  max_tickets_per_buyer: number | null
  gateway_fee_basis_points: number
}

const base = (editionId: string) => `/editions/${editionId}/ticketing`

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new TicketsError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new TicketsError('Request failed')
  }
}

export default {
  types: (editionId: string) => call(() => httpClient.get<{ data: TicketTypePayload[], meta: SettingsPayload }>(`${base(editionId)}/ticket-types`)),
  createType: (editionId: string, body: TicketTypeBody) => call(() => httpClient.post<{ data: TicketTypePayload }>(`${base(editionId)}/ticket-types`, { body })),
  updateType: (editionId: string, typeId: string, body: Partial<TicketTypeBody> & { position?: number }) =>
    call(() => httpClient.patch<{ data: TicketTypePayload }>(`${base(editionId)}/ticket-types/${typeId}`, { body })),
  deleteType: (editionId: string, typeId: string) => call(() => httpClient.delete<null>(`${base(editionId)}/ticket-types/${typeId}`)),
  createBatch: (editionId: string, typeId: string, body: BatchBody) =>
    call(() => httpClient.post<{ data: BatchPayload }>(`${base(editionId)}/ticket-types/${typeId}/batches`, { body })),
  updateBatch: (editionId: string, typeId: string, batchId: string, body: Partial<BatchBody>) =>
    call(() => httpClient.patch<{ data: BatchPayload }>(`${base(editionId)}/ticket-types/${typeId}/batches/${batchId}`, { body })),
  closeBatch: (editionId: string, typeId: string, batchId: string) =>
    call(() => httpClient.post<{ data: BatchPayload }>(`${base(editionId)}/ticket-types/${typeId}/batches/${batchId}/close`)),
  deleteBatch: (editionId: string, typeId: string, batchId: string) =>
    call(() => httpClient.delete<null>(`${base(editionId)}/ticket-types/${typeId}/batches/${batchId}`)),
  updateSettings: (editionId: string, body: SettingsBody) => call(() => httpClient.patch<{ data: SettingsPayload }>(`${base(editionId)}/settings`, { body })),
  coupons: (editionId: string) => call(() => httpClient.get<{ data: CouponPayload[] }>(`${base(editionId)}/coupons`)),
  createCoupon: (editionId: string, body: CouponBody) => call(() => httpClient.post<{ data: CouponPayload }>(`${base(editionId)}/coupons`, { body })),
  updateCoupon: (editionId: string, couponId: string, body: Partial<CouponBody>) =>
    call(() => httpClient.patch<{ data: CouponPayload }>(`${base(editionId)}/coupons/${couponId}`, { body })),
  deleteCoupon: (editionId: string, couponId: string) => call(() => httpClient.delete<null>(`${base(editionId)}/coupons/${couponId}`)),
}
