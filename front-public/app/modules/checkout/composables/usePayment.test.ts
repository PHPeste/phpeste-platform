import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import checkoutService from '../services/checkoutService'
import { CheckoutError } from '../services/checkoutError'
import { POLL_DELAYS, usePayment } from './usePayment'
import { saveOrderToken } from './storage'
import { paymentPayload, receiverPayload } from './fixtures'

vi.mock('../services/checkoutService', () => ({ default: { receiver: vi.fn(), payment: vi.fn(), startPayment: vi.fn(), tickets: vi.fn() } }))

beforeEach(() => {
  vi.clearAllMocks()
  localStorage.clear()
  saveOrderToken('o-1', 'tok')
  vi.mocked(checkoutService.receiver).mockResolvedValue({ data: receiverPayload() })
  vi.mocked(checkoutService.payment).mockResolvedValue({ data: null })
})

afterEach(() => vi.useRealTimers())

describe('usePayment', () => {
  it('quem recebe: Mercado Pago com chave pública tokeniza o cartão no navegador; Asaas usa a página do gateway', async () => {
    const asaas = usePayment('ed-1', 'o-1')
    await asaas.load()
    expect(asaas.receiver.value).toMatchObject({ holderName: 'Fulana de Tal', maxInstallments: 3, cardInBrowser: false })

    vi.mocked(checkoutService.receiver).mockResolvedValue({ data: receiverPayload({ driver: 'mercadopago', public_key: 'APP_USR-123' }) })
    const mp = usePayment('ed-1', 'o-1')
    await mp.load()
    expect(mp.receiver.value?.cardInBrowser).toBe(true)
  })

  it('duplo clique: só uma cobrança sai do navegador, e repetir a mesma escolha usa a mesma chave', async () => {
    let release: (value: unknown) => void = () => {}
    vi.mocked(checkoutService.startPayment).mockReturnValueOnce(new Promise((resolve) => { release = resolve }) as never)
    const flow = usePayment('ed-1', 'o-1')

    const first = flow.start('pix')
    const second = flow.start('pix')
    release({ data: paymentPayload({ status: 'failed', failure_reason: 'Recusado.' }) })
    expect(await second).toBe(false)
    await first
    expect(checkoutService.startPayment).toHaveBeenCalledTimes(1)

    vi.mocked(checkoutService.startPayment).mockRejectedValueOnce(new CheckoutError('offline'))
    vi.mocked(checkoutService.startPayment).mockResolvedValueOnce({ data: paymentPayload() })
    await flow.start('pix')
    await flow.start('pix')
    const keys = vi.mocked(checkoutService.startPayment).mock.calls.map((call) => call[4])
    expect(keys[1]).toBeDefined()
    expect(keys[2]).toBeDefined()
    flow.stop()
  })

  it('Pix pendente: consulta com espera crescente até ser pago', async () => {
    vi.useFakeTimers()
    vi.mocked(checkoutService.startPayment).mockResolvedValue({ data: paymentPayload() })
    vi.mocked(checkoutService.payment)
      .mockResolvedValueOnce({ data: paymentPayload() })
      .mockResolvedValueOnce({ data: paymentPayload({ status: 'paid', order_status: 'paid' }) })
    const flow = usePayment('ed-1', 'o-1')
    await flow.start('pix')
    expect(flow.payment.value?.pix?.copyPaste).toBe('00020126pix-copia-e-cola')

    await vi.advanceTimersByTimeAsync(POLL_DELAYS[0]! - 1)
    expect(checkoutService.payment).toHaveBeenCalledTimes(0)
    await vi.advanceTimersByTimeAsync(1)
    expect(checkoutService.payment).toHaveBeenCalledTimes(1)
    await vi.advanceTimersByTimeAsync(POLL_DELAYS[1]!)
    expect(checkoutService.payment).toHaveBeenCalledTimes(2)
    expect(flow.paid.value).toBe(true)

    await vi.advanceTimersByTimeAsync(60_000)
    expect(checkoutService.payment).toHaveBeenCalledTimes(2)
  })

  it('cartão na página do gateway: manda as parcelas e segue para o endereço do gateway', async () => {
    const assign = vi.fn()
    vi.stubGlobal('location', { ...window.location, assign })
    vi.mocked(checkoutService.startPayment).mockResolvedValue({ data: paymentPayload({ method: 'card', pix: null, installments: 3, checkout_url: 'https://sandbox.asaas.com/i/abc' }) })
    const flow = usePayment('ed-1', 'o-1')

    await flow.start('card', 3)
    expect(vi.mocked(checkoutService.startPayment).mock.calls[0]![3]).toEqual({ method: 'card', installments: 3 })
    expect(assign).toHaveBeenCalledWith('https://sandbox.asaas.com/i/abc')
    vi.unstubAllGlobals()
  })

  it('cartão tokenizado: só o token, a bandeira e o emissor vão para a API; recusa mostra o motivo seguro', async () => {
    vi.mocked(checkoutService.startPayment).mockResolvedValue({ data: paymentPayload({ method: 'card', pix: null, status: 'failed', failure_reason: 'Cartão recusado pelo banco emissor.' }) })
    const flow = usePayment('ed-1', 'o-1')

    await flow.start('card', 2, { token: 'tok_card', brand: 'visa', issuer: '25', installments: 2 })
    expect(vi.mocked(checkoutService.startPayment).mock.calls[0]![3]).toEqual({ method: 'card', installments: 2, card_token: 'tok_card', card_brand: 'visa', card_issuer: '25' })
    expect(flow.failedMessage.value).toBe('Cartão recusado pelo banco emissor.')

    flow.retry()
    expect(flow.payment.value).toBeNull()
  })

  it('erro de validação do backend vira mensagem', async () => {
    vi.mocked(checkoutService.startPayment).mockRejectedValue(new CheckoutError('x', 422, { payment: ['A reserva deste pedido expirou. Escolha os ingressos de novo.'] }))
    const flow = usePayment('ed-1', 'o-1')
    expect(await flow.start('pix')).toBe(false)
    expect(flow.error.value).toBe('A reserva deste pedido expirou. Escolha os ingressos de novo.')
  })
})
