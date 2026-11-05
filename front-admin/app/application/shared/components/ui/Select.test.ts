import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import Select from './Select.vue'

const options = [
  { value: 'cfp', label: 'CFP aberto' },
  { value: 'vendas', label: 'Vendas abertas' },
]

describe('PhSelect', () => {
  it('lista as opções e o texto de seleção', async () => {
    const wrapper = await mountSuspended(Select, { props: { label: 'Etapa', options, placeholder: 'Selecione…' } })
    const texts = wrapper.findAll('option').map((o) => o.text())
    expect(texts).toEqual(['Selecione…', 'CFP aberto', 'Vendas abertas'])
    expect(wrapper.find('label').attributes('for')).toBe(wrapper.find('select').attributes('id'))
  })

  it('emite a opção escolhida', async () => {
    const wrapper = await mountSuspended(Select, { props: { label: 'Etapa', options } })
    await wrapper.find('select').setValue('vendas')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['vendas'])
  })

  it('erro e desabilitado', async () => {
    const wrapper = await mountSuspended(Select, { props: { label: 'Etapa', options, error: 'Escolha uma etapa', disabled: true } })
    expect(wrapper.find('select').attributes('aria-invalid')).toBe('true')
    expect(wrapper.find('select').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[role="alert"]').text()).toBe('Escolha uma etapa')
  })
})
