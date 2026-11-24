import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it, vi } from 'vitest'
import HireModal from './HireModal.vue'
import DeclineModal from './DeclineModal.vue'
import type { SupplierLink } from '../types'

const supplier: SupplierLink = {
  id: 'l-2', supplierId: 's-2', name: 'Buffet Sabor da Terra', service: 'catering', serviceLabel: 'Alimentação', description: null, document: null, website: null, city: null, state: null,
  contactsVisible: true, contacts: [], status: 'quoting', statusLabel: 'Em orçamento', ownerId: null, ownerName: null, quotedCents: 420_000, contractedCents: null,
  acceptedQuoteId: null, declineReason: null, quotesCount: 3, attachments: [], activities: [],
  quotes: [
    { id: 'q-1', amountCents: 960_000, description: 'Coffee break + almoço', receivedOn: '2027-03-01', validUntil: null, expired: false, status: 'received', statusLabel: 'Recebido', recordedBy: null },
    { id: 'q-2', amountCents: 420_000, description: 'Só coffee break', receivedOn: '2027-03-01', validUntil: null, expired: false, status: 'received', statusLabel: 'Recebido', recordedBy: null },
    { id: 'q-3', amountCents: 300_000, description: 'Lanche', receivedOn: '2027-03-01', validUntil: null, expired: false, status: 'rejected', statusLabel: 'Recusado', recordedBy: null },
  ],
}

describe('HireModal', () => {
  it('sugere o menor orçamento não recusado e, sem orçamento, exige o valor', async () => {
    const hire = vi.fn().mockResolvedValue({ ok: true })
    const wrapper = await mountSuspended(HireModal, { props: { modelValue: false, supplier, hire } })
    await wrapper.setProps({ modelValue: true })

    const select = wrapper.find('#hire-quote')
    expect((select.element as HTMLSelectElement).value).toBe('q-2')
    expect(select.findAll('option').map((option) => option.attributes('value'))).toEqual(['q-1', 'q-2', ''])

    await select.setValue('')
    await wrapper.find('#hire-form').trigger('submit')
    expect(hire).not.toHaveBeenCalled()

    await wrapper.find('#hire-value').setValue('5.000')
    await wrapper.find('#hire-form').trigger('submit')
    await vi.waitFor(() => expect(hire).toHaveBeenCalledWith(null, 500_000))
  })
})

describe('DeclineModal', () => {
  it('pede o motivo antes de recusar', async () => {
    const decline = vi.fn().mockResolvedValue({ ok: true })
    const wrapper = await mountSuspended(DeclineModal, { props: { modelValue: false, name: 'Buffet', decline } })
    await wrapper.setProps({ modelValue: true })

    await wrapper.find('#decline-form').trigger('submit')
    expect(decline).not.toHaveBeenCalled()

    await wrapper.find('#decline-reason').setValue('Acima do orçamento')
    await wrapper.find('#decline-form').trigger('submit')
    await vi.waitFor(() => expect(decline).toHaveBeenCalledWith('Acima do orçamento'))
  })
})
