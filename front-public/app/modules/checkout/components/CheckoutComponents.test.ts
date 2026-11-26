import { mountSuspended } from '@nuxt/test-utils/runtime'
import { reactive } from 'vue'
import { describe, expect, it } from 'vitest'
import DataStep from './DataStep.vue'
import OrderSummary from './OrderSummary.vue'
import TicketPicker from './TicketPicker.vue'
import { translateTicket } from '../composables/translate'
import { ticketPayload } from '../composables/fixtures'

const flat = (text: string) => text.replace(/\s/g, ' ')
const inteira = translateTicket(ticketPayload('t-1', 'Inteira', 15000, { pass_fee_to_buyer: true, questions: [
  { id: 'camiseta', label: 'Tamanho da camiseta', type: 'select', required: true, options: ['P', 'M'] },
  { id: 'primeira_vez', label: 'Primeira vez no evento?', type: 'boolean', required: false, options: [] },
], batch: { id: 'b-1', name: '1º lote', price: { amount: 15000, currency: 'BRL' }, ends_at: null, remaining: 1 } }))
const agendado = translateTicket(ticketPayload('t-3', 'Estudante', 7500, { status: 'scheduled', status_label: 'Agendado', batch: null, next_batch: { name: '2º lote', price: { amount: 9000, currency: 'BRL' }, opens_at: '2027-05-01T12:00:00Z' } }))

describe('TicketPicker', () => {
  it('preço, lote, o que resta e quantidade com rótulo; agendado sem seletor', async () => {
    const wrapper = await mountSuspended(TicketPicker, { props: { tickets: [inteira, agendado], quantities: {}, options: () => [0, 1], errors: {} } })
    const items = wrapper.findAll('li')
    expect(flat(items[0]!.text())).toContain('R$ 150,00')
    expect(items[0]!.text()).toContain('Resta 1')
    expect(wrapper.find('label[for="qty-t-1"]').text()).toBe('Quantidade de Inteira')
    expect(items[1]!.find('select').exists()).toBe(false)
    expect(flat(items[1]!.text())).toMatch(/2º lote abre em 1 de maio.* por R\$ 90,00/)

    await wrapper.find('select').setValue('1')
    expect(wrapper.emitted('change')).toEqual([['t-1', 1]])
  })
})

describe('OrderSummary', () => {
  it('desconto do cupom e taxa só na reserva', async () => {
    const wrapper = await mountSuspended(OrderSummary, { props: { lines: [{ type: inteira, quantity: 2, subtotalCents: 30000 }], subtotalCents: 30000, discountCents: 3000, couponCode: 'SERTAO10', totalCents: 27000, feePending: true } })
    const text = flat(wrapper.text())
    expect(text).toContain('2 × Inteira')
    expect(text).toContain('Desconto (SERTAO10)')
    expect(text).toContain('calculada ao reservar')
    expect(text).toContain('Total sem a taxa')
    expect(flat(wrapper.find('[data-testid="summary-total"]').text())).toBe('R$ 270,00')
  })
})

describe('DataStep', () => {
  it('um bloco por ingresso com as perguntas da edição, ids que o resumo de erros encontra e CPF com máscara', async () => {
    const attendees = reactive({ 't-1': [{ name: '', email: '', answers: {} }] })
    const buyer = reactive({ name: '', email: '', document: '' })
    const wrapper = await mountSuspended(DataStep, {
      props: { lines: [{ type: inteira, quantity: 1, subtotalCents: 15000 }], attendees, errors: { 'items.0.attendees.0.answers.camiseta': 'Responda "Tamanho da camiseta".' }, requiresPayment: true, buyer },
    })
    expect(wrapper.find('legend').text()).toBe('Quem paga')
    expect(wrapper.find('[data-testid="attendee-1"] legend').text()).toBe('Ingresso 1 · Inteira')
    expect(wrapper.find('#checkout-items-0-attendees-0-answers-camiseta').attributes('aria-invalid')).toBe('true')
    expect(wrapper.find('[data-testid="checkout-errors"]').text()).toContain('Ingresso 1 · Inteira: Tamanho da camiseta')
    expect(wrapper.findAll('input[type="radio"]')).toHaveLength(2)

    await wrapper.find('#checkout-buyer-document').setValue('52998224725')
    expect(buyer.document).toBe('529.982.247-25')
    expect((wrapper.find('#checkout-buyer-document').element as HTMLInputElement).value).toBe('529.982.247-25')
  })
})
