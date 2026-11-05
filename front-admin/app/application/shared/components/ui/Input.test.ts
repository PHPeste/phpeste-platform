import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import Input from './Input.vue'

describe('PhInput', () => {
  it('associa o rótulo ao campo pelo id', async () => {
    const wrapper = await mountSuspended(Input, { props: { label: 'Nome da edição' } })
    const input = wrapper.find('input')
    expect(wrapper.find('label').attributes('for')).toBe(input.attributes('id'))
  })

  it('atualiza o v-model ao digitar', async () => {
    const wrapper = await mountSuspended(Input, {
      props: { label: 'Nome', modelValue: '', 'onUpdate:modelValue': (v: string | number) => wrapper.setProps({ modelValue: v }) },
    })
    await wrapper.find('input').setValue('PHPeste 2027')
    expect(wrapper.props('modelValue')).toBe('PHPeste 2027')
  })

  it('mostra a dica ligada ao campo por aria-describedby', async () => {
    const wrapper = await mountSuspended(Input, { props: { label: 'Nome', hint: 'Aparece no site.' } })
    const describedBy = wrapper.find('input').attributes('aria-describedby')
    expect(describedBy).toBeTruthy()
    expect(wrapper.find(`#${describedBy}`).text()).toBe('Aparece no site.')
  })

  it('erro: marca aria-invalid, anuncia como alerta e esconde a dica', async () => {
    const wrapper = await mountSuspended(Input, {
      props: { label: 'E-mail', hint: 'dica', error: 'E-mail inválido' },
    })
    const input = wrapper.find('input')
    expect(input.attributes('aria-invalid')).toBe('true')
    expect(input.classes()).toContain('input-error')
    expect(wrapper.find('[role="alert"]').text()).toBe('E-mail inválido')
    expect(wrapper.text()).not.toContain('dica')
  })

  it('desabilitado: o campo fica com o atributo disabled', async () => {
    const wrapper = await mountSuspended(Input, { props: { label: 'Nome', disabled: true } })
    expect(wrapper.find('input').attributes('disabled')).toBeDefined()
  })

  it.each(['date', 'time', 'email'] as const)('aceita o tipo %s', async (type) => {
    const wrapper = await mountSuspended(Input, { props: { label: 'Campo', type } })
    expect(wrapper.find('input').attributes('type')).toBe(type)
  })

  it('obrigatório: indica no rótulo, sem depender só de cor', async () => {
    const wrapper = await mountSuspended(Input, { props: { label: 'Data', required: true } })
    expect(wrapper.find('label').text()).toContain('obrigatório')
  })
})
