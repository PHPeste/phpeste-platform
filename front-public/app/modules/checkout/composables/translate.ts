import type { OrderPayload, PaymentPayload, QuotePayload, ReceiverPayload, TicketPayload, TicketTypePayload } from '../services/checkoutService'
import type { IssuedTicket, Order, Payment, Quote, Receiver, TicketOption } from '../types'

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

export const translateReceiver = (payload: ReceiverPayload): Receiver => ({
  driver: payload.driver,
  publicKey: payload.public_key,
  holderName: payload.holder_name,
  holderType: payload.holder_type,
  methods: payload.methods.filter((method): method is 'pix' | 'card' => method === 'pix' || method === 'card'),
  maxInstallments: Math.max(1, payload.max_installments),
  sandbox: payload.sandbox,
  ready: payload.ready,
  cardInBrowser: payload.driver === 'mercadopago' && !!payload.public_key,
})

export const translatePayment = (payload: PaymentPayload): Payment => ({
  id: payload.id,
  method: payload.method,
  status: payload.status,
  amountCents: payload.amount.amount,
  installments: payload.installments,
  pix: payload.pix ? { copyPaste: payload.pix.copy_paste, qrImage: payload.pix.qr_code_base64 } : null,
  checkoutUrl: payload.checkout_url,
  expiresAt: payload.expires_at,
  failureReason: payload.failure_reason,
  orderStatus: payload.order_status,
})

export const translateIssuedTicket = (payload: TicketPayload): IssuedTicket => ({
  id: payload.id,
  code: payload.code,
  typeName: payload.ticket_type_name,
  holderName: payload.holder.name,
  qr: payload.qr ?? null,
})
