import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import BatchModal from './BatchModal.vue'
import TicketCardPreview from './TicketCardPreview.vue'
import type { Batch, TicketType } from '../types'

const batch: Batch = { id: 'b-1', ticketTypeId: 'tt-1', name: '1º lote', position: 0, priceCents: 15000, quantity: 100, sold: 12, remaining: 88, startsAt: null, endsAt: null, onSellOut: 'next', status: 'on_sale', statusLabel: 'À venda' }
const type: TicketType = { id: 'tt-1', name: 'Inteira', description: '', visibility: 'public', visibilityLabel: 'Qualquer pessoa', accessCode: '', minPerOrder: 1, maxPerOrder: 10, showRemaining: true, passFeeToBuyer: false, questions: [], position: 0, sold: 12, quantity: 100, currentBatchId: 'b-1', batches: [batch] }

describe('TicketCardPreview', () => {
  it('mostra o ingresso como no checkout, com a taxa repassada e quantos restam', async () => {
    const wrapper = await mountSuspended(TicketCardPreview, { props: { name: 'Inteira', description: 'Acesso aos dois dias', visibility: 'code', showRemaining: true, passFeeToBuyer: true, feeBasisPoints: 499, batch } })
    const text = wrapper.text()

    expect(text).toContain('Inteira')
    expect(text).toContain('Acesso aos dois dias')
    expect(text).toContain('R$ 157,49')
    expect(text).toContain('+ R$ 7,49 de taxa')
    expect(text).toContain('Restam 88')
    expect(text).toContain('Só com código')
  })
})

describe('BatchModal', () => {
  it('calcula o valor líquido ao vivo e avisa que pedidos existentes não mudam ao trocar o preço', async () => {
    const wrapper = await mountSuspended(BatchModal, { props: { modelValue: false, type, batch, feeBasisPoints: 499, save: async () => ({ ok: true }) } })
    await wrapper.setProps({ modelValue: true })

    expect(wrapper.find('[data-testid="net-calculation"]').text()).toContain('R$ 142,51')
    expect(wrapper.find('[data-testid="price-change-warning"]').exists()).toBe(false)

    await wrapper.find('#batch-price').setValue('170,00')

    expect(wrapper.find('[data-testid="net-calculation"]').text()).toContain('R$ 161,51')
    expect(wrapper.find('[data-testid="price-change-warning"]').text()).toContain('12 ingressos vendidos ou reservados')
  })
})
