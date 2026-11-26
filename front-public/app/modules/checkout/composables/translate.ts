import type { OrderPayload, QuotePayload, TicketTypePayload } from '../services/checkoutService'
import type { Order, Quote, TicketOption } from '../types'

export const translateTicket = (payload: TicketTypePayload): TicketOption => ({
  id: payload.id,
  name: payload.name,
  description: payload.description,
  min: payload.min_per_order,
  max: payload.max_per_order,
  passFee: payload.pass_fee_to_buyer,
  questions: payload.questions.map((question) => ({ ...question, options: question.options ?? [] })),
  onSale: payload.status === 'on_sale' && payload.batch !== null,
  statusLabel: payload.status_label,
  batchName: payload.batch?.name ?? null,
  priceCents: payload.batch?.price.amount ?? null,
  remaining: payload.batch?.remaining ?? null,
  endsAt: payload.batch?.ends_at ?? null,
  nextBatch: payload.next_batch ? { name: payload.next_batch.name, priceCents: payload.next_batch.price.amount, opensAt: payload.next_batch.opens_at } : null,
})

export const translateQuote = (payload: QuotePayload): Quote => ({
  code: payload.code,
  subtotalCents: payload.subtotal.amount,
  discountCents: payload.discount.amount,
  totalCents: payload.total.amount,
})

export const translateOrder = (payload: OrderPayload): Order => ({
  id: payload.id,
  number: payload.number,
  status: payload.status,
  statusLabel: payload.status_label,
  buyer: { name: payload.buyer.name, email: payload.buyer.email },
  subtotalCents: payload.subtotal.amount,
  discountCents: payload.discount.amount,
  feeCents: payload.fee.amount,
  totalCents: payload.total.amount,
  requiresPayment: payload.requires_payment,
  couponCode: payload.coupon_code,
  expiresAt: payload.expires_at,
  items: payload.items.map((item) => ({
    id: item.id,
    ticketTypeName: item.ticket_type_name,
    batchName: item.batch_name,
    unitPriceCents: item.unit_price.amount,
    discountCents: item.discount.amount,
    feeCents: item.fee.amount,
    totalCents: item.total.amount,
    attendee: { name: item.attendee.name, email: item.attendee.email },
  })),
})
