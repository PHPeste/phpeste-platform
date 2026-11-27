import type { OrderPayload, TicketPayload } from '../services/accountService'

const money = (amount: number) => ({ amount, currency: 'BRL' })

export const ticketPayload = (overrides: Partial<TicketPayload> = {}): TicketPayload => ({
  id: 'tk-1', code: 'K7QM-4XTD', status: 'active', status_label: 'Válido', order_id: 'o-1', order_number: 42, ticket_type_name: 'Inteira', batch_name: '1º lote',
  holder: { name: 'Ada Lovelace', email: 'ada@x.dev', badge_name: null, company: null, tshirt_size: null, accessibility: null },
  missing: ['badge_name', 'tshirt_size'], issued_at: '2027-06-10T12:00:00Z', cancelled_at: null, qr: 'PHT1.tk-1.1.abc',
  ...overrides,
})

export const orderPayload = (overrides: Partial<OrderPayload> = {}): OrderPayload => ({
  id: 'o-1', number: 42, status: 'paid', status_label: 'Pago', subtotal: money(30000), discount: money(3000), fee: money(1080), total: money(28080), refunded: money(15000),
  coupon_code: 'SERTAO10', created_at: '2027-06-10T12:00:00Z', paid_at: '2027-06-10T12:05:00Z', expires_at: null,
  items: [{ id: 'i-1', ticket_type_name: 'Inteira', batch_name: '1º lote', attendee_name: 'Ada Lovelace', total: money(14040), cancelled_at: null }],
  refunds: [{ id: 'r-1', amount: money(15000), status: 'completed', status_label: 'Concluído', reason_label: 'Pedido de quem comprou', created_at: '2027-06-11T10:00:00Z', completed_at: '2027-06-11T10:01:00Z' }],
  ...overrides,
})
