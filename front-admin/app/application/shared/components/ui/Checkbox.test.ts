import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import Checkbox from './Checkbox.vue'

describe('PhCheckbox', () => {
  it('rótulo clicável ligado à caixa', async () => {
    const wrapper = await mountSuspended(Checkbox, { props: { label: 'Mostrar no site' } })
    expect(wrapper.find('label').attributes('for')).toBe(wrapper.find('input').attributes('id'))
  })

  it('emite true ao marcar', async () => {
    const wrapper = await mountSuspended(Checkbox, { props: { label: 'Mostrar', modelValue: false } })
    await wrapper.find('input').setValue(true)
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([true])
  })

  it('desabilitado, com dica descrevendo o campo', async () => {
    const wrapper = await mountSuspended(Checkbox, { props: { label: 'Mostrar', hint: 'Pode mudar depois.', disabled: true } })
    const input = wrapper.find('input')
    expect(input.attributes('disabled')).toBeDefined()
    expect(wrapper.find(`#${input.attributes('aria-describedby')}`).text()).toBe('Pode mudar depois.')
  })
})
