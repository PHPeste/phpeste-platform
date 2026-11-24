import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it, vi } from 'vitest'
import BudgetCard from './BudgetCard.vue'
import ExpenseModal from './ExpenseModal.vue'
import { translateSummary } from '../composables/useFinance'
import { summaryPayload } from '../composables/fixtures'

const flat = (text: string) => text.replace(/\s/g, ' ')

describe('BudgetCard', () => {
  it('cada barra tem o texto equivalente e o estouro aparece por escrito', async () => {
    const wrapper = await mountSuspended(BudgetCard, { props: { summary: translateSummary(summaryPayload().data as never), disabled: false } })
    const meters = wrapper.findAll('[role="meter"]')

    expect(meters).toHaveLength(2)
    expect(flat(meters[0]!.attributes('aria-valuetext')!)).toBe('R$ 9.000 de R$ 18.000')
    expect(flat(wrapper.text())).toContain('Passou R$ 400 do orçamento')
    expect(flat(wrapper.text())).toContain('R$ 0, sem orçamento')
    expect(flat(wrapper.text())).toContain('Total: R$ 11.400 de R$ 20.000')
  })
})

describe('ExpenseModal', () => {
  it('paga exige a data, que já vem com hoje, e valor inválido não salva', async () => {
    const save = vi.fn().mockResolvedValue({ ok: true })
    const wrapper = await mountSuspended(ExpenseModal, {
      props: { modelValue: false, expense: null, categories: [{ id: 'cat-local', name: 'Local', budgetCents: null }], suppliers: [], save },
    })
    await wrapper.setProps({ modelValue: true })

    await wrapper.find('#expense-description').setValue('Sinal do espaço')
    await wrapper.find('#expense-amount').setValue('nove mil')
    await wrapper.find('#expense-form').trigger('submit')
    expect(save).not.toHaveBeenCalled()

    await wrapper.find('#expense-amount').setValue('9.000')
    await wrapper.find('#expense-status').setValue('paid')
    expect((wrapper.find('#expense-paidOn').element as HTMLInputElement).value).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    await wrapper.find('#expense-form').trigger('submit')
    await vi.waitFor(() => expect(save).toHaveBeenCalledWith(null, expect.objectContaining({ amountCents: 900_000, status: 'paid', categoryId: 'cat-local' }), null))
  })
})
