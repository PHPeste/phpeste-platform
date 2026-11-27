import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import OrderCard from './OrderCard.vue'
import TicketCard from './TicketCard.vue'
import TransferForm from './TransferForm.vue'
import { orderPayload, ticketPayload } from '../composables/fixtures'
import { translateOrder, translateTicket } from '../composables/translate'

const flat = (text: string) => text.replace(/\s+/g, ' ')

describe('TicketCard', () => {
  it('QR com nome de quem usa, código e o que falta no crachá', async () => {
    const wrapper = await mountSuspended(TicketCard, { props: { ticket: translateTicket(ticketPayload()) } })
    expect(wrapper.find('[data-testid=ticket-qr]').attributes('aria-label')).toBe('QR code do ingresso de Ada Lovelace')
    expect(wrapper.text()).toContain('Código K7QM-4XTD')
    expect(wrapper.text()).toContain('Faltam dados do crachá: nome no crachá, camiseta')

    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('save')).toHaveLength(1)
  })

  it('ingresso cancelado não mostra QR nem ações', async () => {
    const wrapper = await mountSuspended(TicketCard, { props: { ticket: translateTicket(ticketPayload({ status: 'cancelled' })) } })
    expect(wrapper.find('[data-testid=ticket-qr]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Ingresso cancelado.')
    expect(wrapper.find('button').exists()).toBe(false)
  })
})

describe('OrderCard', () => {
  it('itens, cupom, taxa, total e reembolso com motivo e status', async () => {
    const wrapper = await mountSuspended(OrderCard, { props: { order: translateOrder(orderPayload()) } })
    const text = flat(wrapper.text())
    expect(text).toContain('Pedido #42')
    expect(text).toContain('Desconto (SERTAO10)')
    expect(text).toContain('R$ 280,80')
    expect(text).toContain('R$ 150,00 · Pedido de quem comprou')
    expect(text).toContain('Concluído')
    expect(wrapper.text()).not.toContain('Continuar pagamento')
  })

  it('pedido aguardando pagamento leva de volta ao checkout', async () => {
    const wrapper = await mountSuspended(OrderCard, { props: { order: translateOrder(orderPayload({ status: 'awaiting_payment', refunds: [], refunded: { amount: 0, currency: 'BRL' } })) } })
    expect(wrapper.find('a').attributes('href')).toBe('/checkout/pedido/o-1')
  })
})

describe('TransferForm', () => {
  it('valida antes de pedir confirmação', async () => {
    const wrapper = await mountSuspended(TransferForm, { props: { currentEmail: 'ada@x.dev', busy: false, errors: {}, message: null } })
    await wrapper.find('form').trigger('submit')
    expect(wrapper.text()).toContain('Informe o nome de quem vai receber.')
    expect(wrapper.text()).toContain('Informe um e-mail válido.')
    expect(wrapper.emitted('transfer')).toBeUndefined()
  })
})
