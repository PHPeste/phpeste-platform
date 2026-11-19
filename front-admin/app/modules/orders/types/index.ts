import type { OrderStatusValue } from '../services/ordersService'

export type OrderStatus = OrderStatusValue

export interface OrderItem {
  id: string
  ticketTypeName: string
  batchName: string | null
  totalCents: number
  attendeeName: string
  attendeeEmail: string
  cancelled: boolean
  externalCode: string | null
}

export interface Order {
  id: string
  number: number
  status: OrderStatus
  statusLabel: string
  externalPlatform: string | null
  buyerName: string
  buyerEmail: string
  buyerDocument: string | null
  subtotalCents: number
  discountCents: number
  feeCents: number
  totalCents: number
  refundedCents: number
  gatewayFeeCents: number
  couponCode: string | null
  expiresAt: string | null
  paidAt: string | null
  createdAt: string
  items: OrderItem[]
  activeTickets: number
  /** "2× Estudante, 1× Inteira", só com os ingressos válidos. */
  itemsSummary: string
}

export interface OrderSummary {
  paid: number
  awaiting: number
  awaitingExpiringToday: number
  refunds: number
  refundedCents: number
  grossCents: number
  gatewayFeeCents: number
}

export interface Payment {
  methodLabel: string
  status: string
  statusLabel: string
  installments: number
  expiresAt: string | null
  failureReason: string | null
}

export type RefundStatus = 'unconfirmed' | 'pending' | 'completed' | 'failed'

export interface Refund {
  id: string
  amountCents: number
  status: RefundStatus
  statusLabel: string
  reasonLabel: string
  note: string | null
  cancelledItems: number
  failureReason: string | null
  createdAt: string
}

export interface HistoryEntry {
  id: string
  kind: string
  title: string
  detail: string | null
  actorName: string | null
  at: string
}

export interface OrderDetail {
  order: Order
  payment: Payment | null
  refunds: Refund[]
  history: HistoryEntry[]
}

export interface RefundInput {
  amountCents: number | null
  reason: string
  note: string
  cancelTickets: boolean
  itemIds: string[] | null
}

export type MutationResult = { ok: true } | { ok: false, message: string, fieldErrors: Record<string, string> }

export type RefundResult = { ok: true, refund: Refund } | { ok: false, message: string, fieldErrors: Record<string, string> }
