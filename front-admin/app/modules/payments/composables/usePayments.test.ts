import { beforeEach, describe, expect, it, vi } from 'vitest'
import paymentsService from '../services/paymentsService'
import { PaymentsError } from '../services/paymentsError'
import { usePayments } from './usePayments'
import type { AccountInput } from '../types'

vi.mock('../services/paymentsService', () => ({
  default: { accounts: vi.fn(), create: vi.fn(), update: vi.fn(), verify: vi.fn(), events: vi.fn(), reprocess: vi.fn() },
}))

const account = (overrides: Record<string, unknown> = {}) => ({
  id: 'a-1', driver: 'asaas', environment: 'sandbox', environment_label: 'Sandbox (testes)', credentials_hint: '••••abcd',
  holder: { type: 'individual', type_label: 'Pessoa física (CPF)', name: 'Fulana', document: '***.982.247-**' },
  methods: [{ key: 'pix', label: 'Pix' }, { key: 'card', label: 'Cartão de crédito' }], max_installments: 3, pix_expiration_minutes: 30,
  active: true, ready: false, verified_at: null, webhook_verified_at: null, webhook_rejections: { count: 0, last_at: null },
  retired_at: null, created_at: '2027-06-01T12:00:00Z', webhook_url: 'https://api.test/api/v1/webhooks/payments/tok', last_event_at: null,
  ...overrides,
})
const event = { id: 'e-1', payment_account_id: 'a-1', driver: 'asaas', event_id: 'evt_1', type: 'PAYMENT_RECEIVED', gateway_charge_id: 'pay_1', status: 'failed', status_label: 'Com falha', result: 'Cobrança não encontrada', attempts: 3, can_reprocess: true, next_attempt_at: null, processed_at: null, received_at: '2027-06-01T12:00:00Z' }
const input: AccountInput = { driver: 'asaas', environment: 'sandbox', apiKey: ' chave_api_1234 ', webhookSecret: 'segredo-123', publicKey: '', holderType: 'individual', holderName: 'Fulana', holderDocument: '529.982.247-25', methods: ['pix'], maxInstallments: 6, pixExpirationMinutes: 30 }

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(paymentsService.accounts).mockResolvedValue({ data: { active: account(), previous: [account({ id: 'a-0', active: false, retired_at: '2027-05-01T12:00:00Z', webhook_url: null })], drivers: [{ key: 'asaas', label: 'Asaas', needs_public_key: false }, { key: 'mercadopago', label: 'Mercado Pago', needs_public_key: true }] } } as never)
  vi.mocked(paymentsService.events).mockResolvedValue({ data: [event], meta: { current_page: 1, last_page: 2, total: 26 } } as never)
})

describe('usePayments', () => {
  it('carrega a conta ativa com o nome do gateway, as anteriores e os eventos', async () => {
    const payments = usePayments('ed-1')
    await Promise.all([payments.load(), payments.loadEvents(1, 'failed')])

    expect(payments.state.value).toBe('ready')
    expect(payments.active.value).toMatchObject({ driverLabel: 'Asaas', methods: ['pix', 'card'], credentialsHint: '••••abcd', ready: false })
    expect(payments.previous.value[0]).toMatchObject({ id: 'a-0', active: false })
    expect(payments.drivers.value[1]).toEqual({ key: 'mercadopago', label: 'Mercado Pago', needsPublicKey: true })
    expect(payments.events.value[0]).toMatchObject({ eventId: 'evt_1', canReprocess: true })
    expect(payments.eventsPageCount.value).toBe(2)
    expect(paymentsService.events).toHaveBeenCalledWith('ed-1', 1, 'failed')
  })

  it('cadastra sem chave pública quando o gateway não pede e parcela 1x sem cartão', async () => {
    const payments = usePayments('ed-1')
    await payments.load()
    vi.mocked(paymentsService.create).mockResolvedValue({ data: account() } as never)

    expect(await payments.create(input)).toEqual({ ok: true })
    const body = vi.mocked(paymentsService.create).mock.calls[0]![1]
    expect(body).toMatchObject({ api_key: 'chave_api_1234', max_installments: 1, confirm: false })
    expect(body).not.toHaveProperty('public_key')
  })

  it('pede confirmação quando a conta atual já recebeu pedidos pagos', async () => {
    const payments = usePayments('ed-1')
    await payments.load()
    vi.mocked(paymentsService.create).mockRejectedValue(new PaymentsError('x', 422, { confirm: ['Esta conta já recebeu 3 pedidos pagos.'] }))

    const result = await payments.create(input)
    expect(result).toMatchObject({ ok: false, needsConfirm: true, fieldErrors: { confirm: 'Esta conta já recebeu 3 pedidos pagos.' } })

    vi.mocked(paymentsService.create).mockResolvedValue({ data: account() } as never)
    await payments.create(input, true)
    expect(vi.mocked(paymentsService.create).mock.calls[1]![1]).toMatchObject({ confirm: true })
  })

  it('testar conexão atualiza a conta ou devolve a mensagem do gateway', async () => {
    const payments = usePayments('ed-1')
    await payments.load()

    vi.mocked(paymentsService.verify).mockRejectedValueOnce(new PaymentsError('x', 422, { api_key: ['O gateway recusou a chave de API.'] }))
    expect(await payments.verify()).toMatchObject({ ok: false, message: 'O gateway recusou a chave de API.', fieldErrors: { apiKey: 'O gateway recusou a chave de API.' } })

    vi.mocked(paymentsService.verify).mockResolvedValueOnce({ data: account({ verified_at: '2027-06-02T12:00:00Z' }) } as never)
    expect(await payments.verify()).toEqual({ ok: true })
    expect(payments.active.value?.verifiedAt).toBe('2027-06-02T12:00:00Z')
  })

  it('reprocessar troca o evento na lista', async () => {
    const payments = usePayments('ed-1')
    await payments.loadEvents(1, '')
    vi.mocked(paymentsService.reprocess).mockResolvedValue({ data: { ...event, status: 'processed', status_label: 'Processado', can_reprocess: false } } as never)

    const updated = await payments.reprocess(payments.events.value[0]!)
    expect(updated?.statusLabel).toBe('Processado')
    expect(payments.events.value[0]!.canReprocess).toBe(false)
  })
})
