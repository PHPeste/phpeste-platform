import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import Chip from './Chip.vue'

describe('PhChip', () => {
  it('expõe o estado selecionado por aria-pressed', async () => {
    const wrapper = await mountSuspended(Chip, { props: { modelValue: true }, slots: { default: 'Todos' } })
    expect(wrapper.find('button').attributes('aria-pressed')).toBe('true')
  })

  it('alterna ao clicar', async () => {
    const wrapper = await mountSuspended(Chip, { props: { modelValue: false }, slots: { default: 'Pagos' } })
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([true])
  })
})
