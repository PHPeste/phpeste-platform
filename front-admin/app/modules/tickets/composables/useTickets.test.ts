import { beforeEach, describe, expect, it, vi } from 'vitest'
import ticketsService from '../services/ticketsService'
import { TicketsError } from '../services/ticketsError'
import { useTickets } from './useTickets'

vi.mock('../services/ticketsService', () => ({
  default: { types: vi.fn(), createType: vi.fn(), updateType: vi.fn(), deleteType: vi.fn(), createBatch: vi.fn(), updateBatch: vi.fn(), closeBatch: vi.fn(), deleteBatch: vi.fn(), updateSettings: vi.fn(), coupons: vi.fn(), createCoupon: vi.fn(), updateCoupon: vi.fn(), deleteCoupon: vi.fn() },
}))

const batch = (overrides: Record<string, unknown> = {}) => ({
  id: 'b-1', ticket_type_id: 'tt-1', name: '1º lote', position: 0, price: { amount: 15000, currency: 'BRL' }, quantity: 100, allocated: 12, remaining: 88,
  starts_at: null, ends_at: '2027-05-01T02:59:59Z', on_sell_out: 'next', status: 'on_sale', status_label: 'À venda', ...overrides,
})
const typesPayload = (gatewayReady = false, batches = [batch()]) => ({
  data: [{
    id: 'tt-1', name: 'Inteira', description: null, visibility: 'public', visibility_label: 'Qualquer pessoa', access_code: null, min_per_order: 1, max_per_order: 10,
    show_remaining: true, pass_fee_to_buyer: false, questions: [{ label: 'Camiseta', type: 'select', required: true, options: ['P', 'M'] }], position: 0, allocated: 12, quantity: 100, current_batch_id: 'b-1', batches,
  }],
  meta: { gateway_ready: gatewayReady, capacity: 400, allocated: 12, remaining: 388, reservation_minutes: 30, max_tickets_per_buyer: null, gateway_fee_basis_points: 499 },
})
const coupons = { data: [{ id: 'c-1', code: 'COMUNIDADE', description: null, kind: 'percent', kind_label: 'Percentual', value: 20, max_uses: 50, used: 3, remaining: 47, starts_at: null, ends_at: null, ticket_type_ids: [], active: true, status: 'active', discount_total: { amount: 9000, currency: 'BRL' } }] }

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(ticketsService.types).mockResolvedValue(typesPayload() as never)
  vi.mocked(ticketsService.coupons).mockResolvedValue(coupons as never)
})

describe('useTickets', () => {
  it('carrega tipos com lotes em centavos, regras e cupons', async () => {
    const tickets = useTickets('ed-1')
    await tickets.load()

    expect(tickets.state.value).toBe('ready')
    expect(tickets.types.value[0]).toMatchObject({ name: 'Inteira', sold: 12, quantity: 100, description: '', questions: [{ label: 'Camiseta', type: 'select', required: true, options: ['P', 'M'] }] })
    expect(tickets.types.value[0]!.batches[0]).toMatchObject({ priceCents: 15000, sold: 12, remaining: 88, status: 'on_sale' })
    expect(tickets.settings.value).toEqual({ gatewayReady: false, capacity: 400, sold: 12, remaining: 388, reservationMinutes: 30, maxTicketsPerBuyer: null, feeBasisPoints: 499 })
    expect(tickets.coupons.value[0]).toMatchObject({ code: 'COMUNIDADE', discountTotalCents: 9000 })
  })

  it('vendas ficam pausadas só com lote pago ativo e gateway não validado', async () => {
    const tickets = useTickets('ed-1')
    await tickets.load()
    expect(tickets.salesPaused.value).toBe(true)

    vi.mocked(ticketsService.types).mockResolvedValueOnce(typesPayload(true) as never)
    await tickets.load()
    expect(tickets.salesPaused.value).toBe(false)

    vi.mocked(ticketsService.types).mockResolvedValueOnce(typesPayload(false, [batch({ price: { amount: 0, currency: 'BRL' } })]) as never)
    await tickets.load()
    expect(tickets.salesPaused.value).toBe(false)
  })

  it('manda o lote em centavos e o período em UTC, e recarrega', async () => {
    const tickets = useTickets('ed-1')
    await tickets.load()
    vi.mocked(ticketsService.updateBatch).mockResolvedValueOnce({ data: {} } as never)

    const result = await tickets.saveBatch(tickets.types.value[0]!, tickets.types.value[0]!.batches[0]!, { name: ' 1º lote ', priceCents: 17000, quantity: 120, startsAt: '', endsAt: '2027-04-30T23:59', onSellOut: 'close' })

    expect(result).toEqual({ ok: true })
    expect(ticketsService.updateBatch).toHaveBeenCalledWith('ed-1', 'tt-1', 'b-1', expect.objectContaining({ name: '1º lote', price_cents: 17000, quantity: 120, starts_at: null, on_sell_out: 'close' }))
    expect(vi.mocked(ticketsService.updateBatch).mock.calls[0]![3].ends_at).toMatch(/Z$/)
    expect(ticketsService.types).toHaveBeenCalledTimes(2)
  })

  it('só manda o código de acesso quando a visibilidade pede e as opções só em pergunta de lista', async () => {
    const tickets = useTickets('ed-1')
    await tickets.load()
    vi.mocked(ticketsService.createType).mockResolvedValueOnce({ data: {} } as never)

    await tickets.saveType(null, { name: 'VIP', description: '', visibility: 'public', accessCode: 'ESQUECIDO', minPerOrder: 1, maxPerOrder: 2, showRemaining: false, passFeeToBuyer: true, questions: [{ label: 'Empresa ', type: 'text', required: false, options: ['x'] }, { label: 'Camiseta', type: 'select', required: true, options: [' P', '', 'M '] }] })

    expect(ticketsService.createType).toHaveBeenCalledWith('ed-1', expect.objectContaining({
      access_code: null,
      description: null,
      pass_fee_to_buyer: true,
      questions: [{ label: 'Empresa', type: 'text', required: false }, { label: 'Camiseta', type: 'select', required: true, options: ['P', 'M'] }],
    }))
  })

  it('não troca o código de cupom já usado e traduz erro de validação para o campo', async () => {
    const tickets = useTickets('ed-1')
    await tickets.load()
    vi.mocked(ticketsService.updateCoupon).mockRejectedValueOnce(new TicketsError('x', 422, { value: ['O campo valor não pode ser superior a 100.'] }))

    const result = await tickets.saveCoupon(tickets.coupons.value[0]!, { code: 'OUTRO', description: '', kind: 'percent', value: 120, maxUses: null, startsAt: '', endsAt: '', ticketTypeIds: [], active: true })

    expect(vi.mocked(ticketsService.updateCoupon).mock.calls[0]![2]).not.toHaveProperty('code')
    expect(result).toEqual({ ok: false, message: 'O campo valor não pode ser superior a 100.', fieldErrors: { value: 'O campo valor não pode ser superior a 100.' } })
  })

  it('erro ao carregar vira estado de erro', async () => {
    vi.mocked(ticketsService.types).mockRejectedValueOnce(new TicketsError('x', 500))
    const tickets = useTickets('ed-1')
    await tickets.load()
    expect(tickets.state.value).toBe('error')
  })
})
