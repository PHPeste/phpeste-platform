import { describe, expect, it } from 'vitest'
import { orderPayload, ticketPayload } from './fixtures'
import { translateOrder, translateTicket } from './translate'

describe('translate', () => {
  it('ingresso cancelado não leva QR e tamanho desconhecido vira nulo', () => {
    expect(translateTicket(ticketPayload({ status: 'cancelled', holder: { name: 'Ada', email: 'a@x.dev', badge_name: null, company: null, tshirt_size: 'XXXL' } }))).toMatchObject({ active: false, qr: null, tshirtSize: null })
  })

  it('pedido com itens, reembolso e aguardando pagamento', () => {
    expect(translateOrder(orderPayload())).toMatchObject({ number: 42, pending: false, totalCents: 28080, refundedCents: 15000, couponCode: 'SERTAO10', refunds: [{ amountCents: 15000, statusLabel: 'Concluído', reasonLabel: 'Pedido de quem comprou' }] })
    expect(translateOrder(orderPayload({ status: 'awaiting_payment' })).pending).toBe(true)
  })
})
