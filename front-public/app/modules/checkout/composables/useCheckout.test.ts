import { beforeEach, describe, expect, it, vi } from 'vitest'
import checkoutService from '../services/checkoutService'
import { CheckoutError } from '../services/checkoutError'
import { useCheckout } from './useCheckout'
import { saveCart } from './storage'
import { catalogPayload, orderPayload, quotePayload, ticketPayload } from './fixtures'

vi.mock('../services/checkoutService', () => ({ default: { catalog: vi.fn(), checkCoupon: vi.fn(), placeOrder: vi.fn(), order: vi.fn(), cancel: vi.fn() } }))

const shirt = { id: 'camiseta', label: 'Tamanho da camiseta', type: 'select' as const, required: true, options: ['P', 'M', 'G'] }

beforeEach(() => {
  vi.clearAllMocks()
  localStorage.clear()
  vi.mocked(checkoutService.catalog).mockResolvedValue(catalogPayload([
    ticketPayload('t-1', 'Inteira', 15000, { pass_fee_to_buyer: true, questions: [shirt], batch: { id: 'b-1', name: '1º lote', price: { amount: 15000, currency: 'BRL' }, ends_at: null, remaining: 3 } }),
    ticketPayload('t-2', 'Caravana', 10000, { min_per_order: 5, max_per_order: 10 }),
    ticketPayload('t-3', 'Estudante', 7500, { status: 'scheduled', status_label: 'Agendado', batch: null, next_batch: { name: '2º lote', price: { amount: 9000, currency: 'BRL' }, opens_at: '2027-05-01T03:00:00Z' } }),
  ]))
})

async function ready() {
  const checkout = useCheckout('ed-1')
  await checkout.load()
  return checkout
}

describe('useCheckout', () => {
  it('quantidade respeita mínimo, máximo e o que resta; agendado não entra no pedido', async () => {
    const checkout = await ready()
    expect(checkout.quantityOptions(checkout.tickets.value[0]!)).toEqual([0, 1, 2, 3])
    expect(checkout.quantityOptions(checkout.tickets.value[1]!)).toEqual([0, 5, 6, 7, 8, 9, 10])
    expect(checkout.tickets.value[2]).toMatchObject({ onSale: false, nextBatch: { name: '2º lote', priceCents: 9000 } })

    checkout.setQuantity('t-1', 2)
    expect(checkout.subtotalCents.value).toBe(30000)
    expect(checkout.feePending.value).toBe(true)
    expect(checkout.requiresPayment.value).toBe(true)
  })

  it('sem ingresso não avança; com ingresso abre um participante por ingresso', async () => {
    const checkout = await ready()
    expect(checkout.next()).toBe(false)
    expect(checkout.message.value).toBe('Escolha pelo menos um ingresso.')

    checkout.setQuantity('t-1', 2)
    expect(checkout.next()).toBe(true)
    expect(checkout.step.value).toBe(2)
    expect(checkout.attendees.value['t-1']).toHaveLength(2)
  })

  it('cupom é conferido na hora e refeito quando a quantidade muda', async () => {
    const checkout = await ready()
    checkout.couponInput.value = 'sertao10'
    expect(await checkout.applyCoupon()).toBe(false)
    expect(checkout.couponError.value).toBe('Escolha os ingressos antes de aplicar o cupom.')

    checkout.setQuantity('t-1', 1)
    vi.mocked(checkoutService.checkCoupon).mockResolvedValueOnce(quotePayload('SERTAO10', 15000, 1500))
    expect(await checkout.applyCoupon()).toBe(true)
    expect(checkoutService.checkCoupon).toHaveBeenLastCalledWith('ed-1', 'SERTAO10', [{ ticket_type_id: 't-1', quantity: 1 }])
    expect(checkout.totalCents.value).toBe(13500)

    vi.mocked(checkoutService.checkCoupon).mockResolvedValueOnce(quotePayload('SERTAO10', 30000, 3000))
    checkout.setQuantity('t-1', 2)
    await vi.waitFor(() => expect(checkout.discountCents.value).toBe(3000))

    vi.mocked(checkoutService.checkCoupon).mockRejectedValueOnce(new CheckoutError('x', 422, { coupon: ['Cupom esgotado.'] }))
    checkout.removeCoupon()
    checkout.couponInput.value = 'ESGOTADO'
    expect(await checkout.applyCoupon()).toBe(false)
    expect(checkout.couponError.value).toBe('Cupom esgotado.')
    expect(checkout.discountCents.value).toBe(0)
  })

  it('valida comprador, CPF quando há pagamento e perguntas obrigatórias antes de chamar a API', async () => {
    const checkout = await ready()
    checkout.setQuantity('t-1', 1)
    checkout.next()
    checkout.buyer.value = { name: 'Ada Lovelace', email: 'ada@x', document: '529.982.247-24' }

    expect(await checkout.reserve()).toBeNull()
    expect(checkout.errors.value).toEqual({
      'buyer.email': 'Confira o e-mail: falta algo como nome@exemplo.com.',
      'buyer.document': 'Confira o CPF: os números não batem.',
      'items.0.attendees.0.name': 'Preencha este campo.',
      'items.0.attendees.0.email': 'Preencha este campo.',
      'items.0.attendees.0.answers.camiseta': 'Responda "Tamanho da camiseta".',
    })
    expect(checkoutService.placeOrder).not.toHaveBeenCalled()
  })

  it('reserva, guarda o token e repete a mesma chave numa nova tentativa igual', async () => {
    const checkout = await ready()
    checkout.setQuantity('t-1', 1)
    checkout.next()
    checkout.buyer.value = { name: 'Ada Lovelace', email: 'ada@x.dev', document: '529.982.247-25' }
    checkout.copyBuyer('t-1', 0)
    checkout.attendees.value['t-1']![0]!.answers.camiseta = 'M'

    vi.mocked(checkoutService.placeOrder).mockRejectedValueOnce(new CheckoutError('offline'))
    expect(await checkout.reserve()).toBeNull()
    expect(checkout.message.value).toBe('Sem conexão com a internet. Confira e tente de novo.')

    vi.mocked(checkoutService.placeOrder).mockResolvedValueOnce({ data: orderPayload({ access_token: 'tok-1' }) })
    expect(await checkout.reserve()).toBe('o-1')
    const [first, second] = vi.mocked(checkoutService.placeOrder).mock.calls
    expect(first![2]).toBe(second![2])
    expect(second![1]).toEqual({
      buyer: { name: 'Ada Lovelace', email: 'ada@x.dev', document: '529.982.247-25' },
      coupon: null,
      items: [{ ticket_type_id: 't-1', attendees: [{ name: 'Ada Lovelace', email: 'ada@x.dev', answers: { camiseta: 'M' } }] }],
    })
    expect(localStorage.getItem('phpeste.checkout.order.o-1')).toBe('tok-1')
  })

  it('erro do backend vai para o campo certo, inclusive o CPF', async () => {
    const checkout = await ready()
    checkout.setQuantity('t-1', 1)
    checkout.next()
    checkout.buyer.value = { name: 'Ada Lovelace', email: 'ada@x.dev', document: '529.982.247-25' }
    checkout.copyBuyer('t-1', 0)
    checkout.attendees.value['t-1']![0]!.answers.camiseta = 'M'
    vi.mocked(checkoutService.placeOrder).mockRejectedValueOnce(new CheckoutError('x', 422, { buyer_document: ['Informe o CPF de quem paga.'], items: ['Cada pessoa compra no máximo 2 ingressos nesta edição.'] }))

    expect(await checkout.reserve()).toBeNull()
    expect(checkout.errors.value).toEqual({ 'buyer.document': 'Informe o CPF de quem paga.' })
    expect(checkout.message.value).toBe('Cada pessoa compra no máximo 2 ingressos nesta edição.')
  })

  it('depois de uma reserva expirada, volta com a escolha anterior', async () => {
    saveCart('ed-1', { quantities: { 't-1': 2, 't-3': 1 }, coupon: 'SERTAO10' })
    vi.mocked(checkoutService.checkCoupon).mockResolvedValue(quotePayload('SERTAO10', 30000, 3000))
    const checkout = useCheckout('ed-1')
    await checkout.load(true)

    expect(checkout.quantities.value).toEqual({ 't-1': 2 })
    expect(checkout.quote.value?.code).toBe('SERTAO10')
  })
})
