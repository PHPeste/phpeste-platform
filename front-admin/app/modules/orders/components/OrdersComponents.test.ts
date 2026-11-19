import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it, vi } from 'vitest'
import MarkPaidModal from './MarkPaidModal.vue'
import RefundModal from './RefundModal.vue'
import type { Order } from '../types'

const order: Order = {
  id: 'o-1', number: 42, status: 'paid', statusLabel: 'Pago', externalPlatform: null, buyerName: 'Ana Souza', buyerEmail: 'ana@exemplo.com', buyerDocument: null,
  subtotalCents: 30000, discountCents: 0, feeCents: 0, totalCents: 30000, refundedCents: 0, gatewayFeeCents: 1497, couponCode: null, expiresAt: null, paidAt: null, createdAt: '2027-06-10T12:00:00Z',
  items: [
    { id: 'i-1', ticketTypeName: 'Inteira', batchName: '1º lote', totalCents: 15000, attendeeName: 'Ana Souza', attendeeEmail: 'ana@exemplo.com', cancelled: false, externalCode: null },
    { id: 'i-2', ticketTypeName: 'Inteira', batchName: '1º lote', totalCents: 15000, attendeeName: 'Bia Lima', attendeeEmail: 'bia@exemplo.com', cancelled: false, externalCode: null },
  ],
  activeTickets: 2,
}
const flat = (text: string) => text.replace(/\s/g, ' ')

describe('RefundModal', () => {
  it('avisa sobre taxa e prazo, pede confirmação explícita e mostra o resultado do estorno', async () => {
    const refund = vi.fn().mockResolvedValue({ ok: true, refund: { id: 'r-1', amountCents: 5000, status: 'pending', statusLabel: 'Em processamento no gateway', reasonLabel: '', note: null, cancelledItems: 0, failureReason: null, createdAt: '' } })
    const wrapper = await mountSuspended(RefundModal, { props: { modelValue: false, order, refund } })
    await wrapper.setProps({ modelValue: true })

    expect(flat(wrapper.find('[data-testid="refund-fee-warning"]').text())).toContain('R$ 14,97')

    await wrapper.find('input[value="partial"]').setValue(true)
    await wrapper.find('#refund-amount').setValue('400,00')
    await wrapper.find('#refund-form').trigger('submit')
    expect(flat(wrapper.text())).toContain('até R$ 300,00')

    await wrapper.find('#refund-amount').setValue('50,00')
    await wrapper.find('#refund-form').trigger('submit')
    const confirm = wrapper.find('[data-testid="refund-confirm"]')
    expect(flat(confirm.text())).toContain('devolver R$ 50,00 para Ana Souza')

    const button = wrapper.findAll('button').find((b) => flat(b.text()).includes('Estornar R$ 50,00'))!
    expect(button.attributes('disabled')).toBeDefined()
    await confirm.find('input[type="checkbox"]').setValue(true)
    await button.trigger('click')
    await new Promise((resolve) => setTimeout(resolve, 0))

    expect(refund).toHaveBeenCalledWith({ amountCents: 5000, reason: 'buyer_request', note: '', cancelTickets: false, itemIds: null })
    expect(flat(wrapper.find('[data-testid="refund-result"]').text())).toContain('aceitou o estorno de R$ 50,00')
  })
})

describe('MarkPaidModal', () => {
  it('exige justificativa antes de chamar a API', async () => {
    const markPaid = vi.fn().mockResolvedValue({ ok: true })
    const wrapper = await mountSuspended(MarkPaidModal, { props: { modelValue: false, order, markPaid } })
    await wrapper.setProps({ modelValue: true })

    await wrapper.find('#mark-paid-form').trigger('submit')
    expect(wrapper.text()).toContain('pelo menos 10 caracteres')
    expect(markPaid).not.toHaveBeenCalled()

    await wrapper.find('#mark-paid-justification').setValue('Transferência para a conta da comunidade')
    await wrapper.find('#mark-paid-form').trigger('submit')
    expect(markPaid).toHaveBeenCalledWith('Transferência para a conta da comunidade')
    expect(wrapper.emitted('saved')).toHaveLength(1)
  })
})
