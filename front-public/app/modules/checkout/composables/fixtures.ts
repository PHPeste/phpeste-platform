import type { OrderPayload, PaymentPayload, ReceiverPayload, TicketTypePayload } from '../services/checkoutService'

const money = (amount: number) => ({ amount, currency: 'BRL' })

export const ticketPayload = (id: string, name: string, price: number, extra: Partial<TicketTypePayload> = {}): TicketTypePayload => ({
  id,
  name,
  description: null,
  min_per_order: 1,
  max_per_order: 5,
  pass_fee_to_buyer: false,
  questions: [],
  status: 'on_sale',
  status_label: 'À venda',
  batch: { id: `b-${id}`, name: '1º lote', price: money(price), ends_at: null, remaining: null },
  next_batch: null,
  ...extra,
})

export const catalogPayload = (types: TicketTypePayload[], soldOut = false) => ({ data: { sold_out: soldOut, unlocked: false, ticket_types: types } })

export const quotePayload = (code: string, subtotal: number, discount: number) => ({
  data: { code, subtotal: money(subtotal), discount: money(discount), total: money(subtotal - discount), requires_payment: subtotal - discount > 0, lines: [] },
})

export const orderPayload = (extra: Partial<OrderPayload> = {}): OrderPayload => ({
  id: 'o-1',
  number: 42,
  status: 'awaiting_payment',
  status_label: 'Aguardando pagamento',
  buyer: { name: 'Ada Lovelace', email: 'ada@x.dev', document: '***.456.789-**' },
  subtotal: money(30000),
  discount: money(3000),
  fee: money(1080),
  total: money(28080),
  requires_payment: true,
  coupon_code: 'SERTAO10',
  expires_at: '2027-03-10T12:30:00Z',
  paid_at: null,
  items: [
    { id: 'i-1', ticket_type_id: 't-1', ticket_type_name: 'Inteira', batch_name: '1º lote', unit_price: money(15000), discount: money(1500), fee: money(540), total: money(14040), attendee: { name: 'Ada Lovelace', email: 'ada@x.dev' } },
    { id: 'i-2', ticket_type_id: 't-1', ticket_type_name: 'Inteira', batch_name: '1º lote', unit_price: money(15000), discount: money(1500), fee: money(540), total: money(14040), attendee: { name: 'Grace Hopper', email: 'grace@x.dev' } },
  ],
  ...extra,
})

export const receiverPayload = (extra: Partial<ReceiverPayload> = {}): ReceiverPayload => ({
  driver: 'asaas', public_key: null, holder_name: 'Fulana de Tal', holder_type: 'individual', methods: ['pix', 'card'], max_installments: 3, pix_expiration_minutes: 30, sandbox: false, ready: true, ...extra,
})

export const paymentPayload = (extra: Partial<PaymentPayload> = {}): PaymentPayload => ({
  id: 'c-1', method: 'pix', status: 'pending', status_label: 'Pendente', amount: money(28080), installments: 1,
  pix: { copy_paste: '00020126pix-copia-e-cola', qr_code_base64: 'iVBORw0KGgo=' }, checkout_url: null, expires_at: '2027-03-10T12:30:00Z', paid_at: null, failure_reason: null, order_status: 'awaiting_payment', ...extra,
})
