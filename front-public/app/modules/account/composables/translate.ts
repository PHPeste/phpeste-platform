import type { OrderPayload, TicketPayload } from '../services/accountService'
import { TSHIRT_SIZES, type AccountOrder, type AccountTicket, type TshirtSize } from '../types'

const PENDING = ['created', 'awaiting_payment']

const size = (value: string | null): TshirtSize | null => (TSHIRT_SIZES as string[]).includes(value ?? '') ? value as TshirtSize : null

export const translateTicket = (payload: TicketPayload): AccountTicket => ({
  id: payload.id,
  code: payload.code,
  active: payload.status === 'active',
  statusLabel: payload.status_label,
  orderNumber: payload.order_number,
  typeName: payload.ticket_type_name,
  batchName: payload.batch_name,
  holderName: payload.holder.name,
  holderEmail: payload.holder.email,
  badgeName: payload.holder.badge_name,
  company: payload.holder.company,
  tshirtSize: size(payload.holder.tshirt_size),
  accessibility: payload.holder.accessibility ?? null,
  missing: payload.missing,
  qr: payload.status === 'active' ? payload.qr ?? null : null,
  transfers: (payload.transfers ?? []).map((transfer) => ({ fromName: transfer.from.name, toName: transfer.to.name, toEmail: transfer.to.email, at: transfer.at })),
})

export const translateOrder = (payload: OrderPayload): AccountOrder => ({
  id: payload.id,
  number: payload.number,
  status: payload.status,
  statusLabel: payload.status_label,
  pending: PENDING.includes(payload.status),
  subtotalCents: payload.subtotal.amount,
  discountCents: payload.discount.amount,
  feeCents: payload.fee.amount,
  totalCents: payload.total.amount,
  refundedCents: payload.refunded.amount,
  couponCode: payload.coupon_code,
  createdAt: payload.created_at,
  paidAt: payload.paid_at,
  items: payload.items.map((item) => ({ id: item.id, typeName: item.ticket_type_name, batchName: item.batch_name, attendeeName: item.attendee_name, totalCents: item.total.amount, cancelled: item.cancelled_at !== null })),
  refunds: payload.refunds.map((refund) => ({ id: refund.id, amountCents: refund.amount.amount, status: refund.status, statusLabel: refund.status_label, reasonLabel: refund.reason_label, createdAt: refund.created_at, completedAt: refund.completed_at })),
})
