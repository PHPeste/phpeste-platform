import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it, vi } from 'vitest'
import SponsorBoard from './SponsorBoard.vue'
import StageModal from './StageModal.vue'
import PaymentModal from './PaymentModal.vue'
import type { Column, Deal } from '../types'

const deal = (id: string, name: string, stage: Deal['stage'], extra: Partial<Deal> = {}): Deal => ({
  id, sponsorId: `s-${id}`, sponsorName: name, tierId: 't-1', tierName: 'Ouro', stage, stageLabel: stage, valueCents: 800_000, receivedCents: 0, outstandingCents: 800_000,
  ownerId: null, ownerName: null, lostReason: null, position: 0, paidAt: null, lastContact: null, payments: [], deliverables: [], ...extra,
})
const columns: Column[] = [
  { stage: 'prospecting', label: 'Prospecção', valueCents: 800_000, cards: [deal('d-1', 'Hospedagem Norte', 'prospecting', { lastContact: { summary: 'Contato feito', at: '' } })] },
  { stage: 'negotiating', label: 'Negociando', valueCents: 0, cards: [] },
  { stage: 'closed', label: 'Fechado', valueCents: 0, cards: [] },
  { stage: 'paid', label: 'Pago', valueCents: 150_000, cards: [deal('d-2', 'Café do Sertão', 'paid', { paidAt: '2027-10-12T12:00:00Z', valueCents: 150_000 })] },
]
const flat = (text: string) => text.replace(/\s/g, ' ')

describe('SponsorBoard', () => {
  it('mostra cota, valor e último contato no card e move pelo "Mover para"', async () => {
    const wrapper = await mountSuspended(SponsorBoard, { props: { columns, disabled: false } })
    const cards = wrapper.findAll('[data-testid="sponsor-card"]')

    expect(flat(cards[0]!.text())).toContain('Ouro · R$ 8.000')
    expect(cards[0]!.text()).toContain('Contato feito')
    expect(flat(cards[1]!.text())).toContain('Pago em 12 de out.')

    await cards[0]!.find('select').setValue('negotiating')
    expect(wrapper.emitted('move')?.[0]).toEqual([columns[0]!.cards[0], 'negotiating'])
  })

  it('arrastar e soltar em outra coluna emite o movimento; edição encerrada não arrasta', async () => {
    const wrapper = await mountSuspended(SponsorBoard, { props: { columns, disabled: false } })
    await wrapper.find('[data-testid="sponsor-card"]').trigger('dragstart', { dataTransfer: { setData: () => {} } })
    await wrapper.find('[data-stage="closed"]').trigger('drop')
    expect(wrapper.emitted('move')?.[0]?.[1]).toBe('closed')

    await wrapper.setProps({ disabled: true })
    expect(wrapper.find('[data-testid="sponsor-card"]').attributes('draggable')).toBe('false')
    expect(wrapper.find('[data-testid="sponsor-card"] select').exists()).toBe(false)
  })
})

describe('StageModal', () => {
  it('perdido pede o motivo; cota sem vaga mostra o aviso e confirma na segunda vez', async () => {
    const move = vi.fn().mockResolvedValueOnce({ ok: false, needsConfirm: true, message: 'A cota Ouro tem 3 vagas.', fieldErrors: { confirm: 'x' } }).mockResolvedValue({ ok: true })
    const wrapper = await mountSuspended(StageModal, { props: { modelValue: false, deal: deal('d-1', 'Cloud Sul', 'negotiating'), company: 'Cloud Sul', move } })
    await wrapper.setProps({ modelValue: true })

    await wrapper.find('#stage-select').setValue('lost')
    await wrapper.find('#stage-form').trigger('submit')
    expect(move).not.toHaveBeenCalled()

    await wrapper.find('#stage-select').setValue('closed')
    await wrapper.find('#stage-form').trigger('submit')
    await vi.waitFor(() => expect(wrapper.find('[data-testid="stage-confirm"]').text()).toContain('A cota Ouro tem 3 vagas.'))

    await wrapper.find('#stage-form').trigger('submit')
    await vi.waitFor(() => expect(move).toHaveBeenLastCalledWith('closed', '', true))
  })
})

describe('PaymentModal', () => {
  it('já vem com o que falta receber e recusa valor inválido', async () => {
    const record = vi.fn().mockResolvedValue({ ok: true })
    const wrapper = await mountSuspended(PaymentModal, { props: { modelValue: false, deal: deal('d-1', 'Cloud Sul', 'closed', { outstandingCents: 400_000 }), record } })
    await wrapper.setProps({ modelValue: true })

    expect((wrapper.find('#payment-amount').element as HTMLInputElement).value).toBe('4.000,00')
    await wrapper.find('#payment-amount').setValue('abc')
    await wrapper.find('#payment-form').trigger('submit')
    expect(record).not.toHaveBeenCalled()

    await wrapper.find('#payment-amount').setValue('4.000')
    await wrapper.find('#payment-form').trigger('submit')
    await vi.waitFor(() => expect(record).toHaveBeenCalledWith(400_000, expect.stringMatching(/^\d{4}-\d{2}-\d{2}$/), ''))
  })
})
