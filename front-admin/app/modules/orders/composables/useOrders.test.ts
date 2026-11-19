import { beforeEach, describe, expect, it, vi } from 'vitest'
import ordersService from '../services/ordersService'
import { OrdersError } from '../services/ordersError'
import { refundableCents, useOrders } from './useOrders'

vi.mock('../services/ordersService', () => ({
  default: { list: vi.fn(), show: vi.fn(), history: vi.fn(), refunds: vi.fn(), cancel: vi.fn(), markPaid: vi.fn(), remind: vi.fn(), refund: vi.fn(), settleRefund: vi.fn(), exportCsv: vi.fn() },
}))

const money = (amount: number) => ({ amount, currency: 'BRL' })
const orderPayload = (overrides: Record<string, unknown> = {}) => ({
  id: 'o-1', number: 42, status: 'paid', status_label: 'Pago', source: 'checkout', external: null,
  buyer: { name: 'Ana Souza', email: 'ana@exemplo.com', document: '***.982.247-**' },
  subtotal: money(30000), discount: money(0), fee: money(0), total: money(30000), refunded: money(10000), gateway_fee: money(1497),
  requires_payment: true, coupon_code: null, expires_at: null, paid_at: '2027-06-10T12:00:00Z', created_at: '2027-06-10T11:50:00Z',
  items: [
    { id: 'i-1', ticket_type_id: 'tt-1', ticket_type_name: 'Inteira', batch_name: '1º lote', unit_price: money(15000), discount: money(0), fee: money(0), total: money(15000), attendee: { name: 'Ana Souza', email: 'ana@exemplo.com' }, cancelled_at: null, external_code: null },
    { id: 'i-2', ticket_type_id: 'tt-1', ticket_type_name: 'Inteira', batch_name: '1º lote', unit_price: money(15000), discount: money(0), fee: money(0), total: money(15000), attendee: { name: 'Bia Lima', email: 'bia@exemplo.com' }, cancelled_at: '2027-06-11T10:00:00Z', external_code: null },
  ],
  ...overrides,
})
const refundPayload = { id: 'r-1', amount: money(10000), status: 'completed', status_label: 'Reembolsado', reason: 'buyer_request', reason_label: 'Pedido de quem comprou', note: null, cancelled_item_ids: ['i-2'], failure_reason: null, created_at: '2027-06-11T10:00:00Z', completed_at: '2027-06-11T10:00:00Z' }

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(ordersService.list).mockResolvedValue({ data: [orderPayload()], meta: { current_page: 1, last_page: 3, total: 61 } } as never)
  vi.mocked(ordersService.show).mockResolvedValue({ data: orderPayload(), meta: { payment: { method: 'pix', method_label: 'Pix', status: 'paid', status_label: 'Pago', installments: 1, expires_at: null, paid_at: null, failure_reason: null } } } as never)
  vi.mocked(ordersService.history).mockResolvedValue({ data: [{ id: 'created', kind: 'created', title: 'Pedido criado', detail: null, actor: null, at: '2027-06-10T11:50:00Z' }, { id: 'h-2', kind: 'refunded', title: 'Reembolso de R$ 100,00', detail: 'Pedido de quem comprou', actor: { id: 'u-1', name: 'Rita' }, at: '2027-06-11T10:00:00Z' }] } as never)
  vi.mocked(ordersService.refunds).mockResolvedValue({ data: [refundPayload] } as never)
})

describe('useOrders', () => {
  it('carrega a página com os totais e traduz o pedido para centavos e ingressos válidos', async () => {
    const orders = useOrders('ed-1')
    await orders.load()

    expect(orders.state.value).toBe('ready')
    expect(orders.pageCount.value).toBe(3)
    expect(orders.total.value).toBe(61)
    expect(orders.orders.value[0]).toMatchObject({ number: 42, totalCents: 30000, refundedCents: 10000, activeTickets: 1, externalPlatform: null })
    expect(refundableCents(orders.orders.value[0]!)).toBe(20000)
  })

  it('filtrar volta para a primeira página', async () => {
    const orders = useOrders('ed-1')
    await orders.goTo(3)
    await orders.search('paid', 'ana')

    expect(ordersService.list).toHaveBeenLastCalledWith('ed-1', { page: 1, status: 'paid', q: 'ana' })
    expect(orders.filtered.value).toBe(true)
  })

  it('abre o pedido com pagamento, reembolsos e histórico', async () => {
    const orders = useOrders('ed-1')
    await orders.open('o-1')

    expect(orders.detailState.value).toBe('ready')
    expect(orders.detail.value?.payment).toMatchObject({ methodLabel: 'Pix', statusLabel: 'Pago' })
    expect(orders.detail.value?.refunds[0]).toMatchObject({ amountCents: 10000, cancelledItems: 1, reasonLabel: 'Pedido de quem comprou' })
    expect(orders.detail.value?.history.map((entry) => entry.actorName)).toEqual([null, 'Rita'])
  })

  it('reembolso total manda o valor nulo e devolve o resultado do estorno; erro 422 vira mensagem do campo', async () => {
    vi.mocked(ordersService.refund).mockResolvedValueOnce({ data: { ...refundPayload, status: 'unconfirmed' } } as never)
    const orders = useOrders('ed-1')
    await orders.open('o-1')

    const result = await orders.refund({ amountCents: null, reason: 'buyer_request', note: '  ', cancelTickets: false, itemIds: ['i-1'] })
    expect(ordersService.refund).toHaveBeenCalledWith('ed-1', 'o-1', { amount_cents: null, reason: 'buyer_request', note: null, cancel_tickets: false, item_ids: null })
    expect(result).toMatchObject({ ok: true, refund: { status: 'unconfirmed' } })

    vi.mocked(ordersService.markPaid).mockRejectedValueOnce(new OrdersError('Inválido', 422, { justification: ['Explique como o pagamento foi recebido.'] }))
    expect(await orders.markPaid('curto')).toEqual({ ok: false, message: 'Explique como o pagamento foi recebido.', fieldErrors: { justification: 'Explique como o pagamento foi recebido.' } })
  })

  it('depois de uma ação recarrega o pedido', async () => {
    vi.mocked(ordersService.remind).mockResolvedValue({ data: { delivery_id: 'd-1' } } as never)
    const orders = useOrders('ed-1')
    await orders.open('o-1')

    expect(await orders.remind()).toEqual({ ok: true })
    expect(ordersService.show).toHaveBeenCalledTimes(2)
  })
})
