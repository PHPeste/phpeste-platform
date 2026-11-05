import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import Modal from './Modal.vue'

describe('PhModal', () => {
  it('fica fechado por padrão e abre com v-model', async () => {
    const wrapper = await mountSuspended(Modal, { props: { title: 'Reembolsar', modelValue: false }, slots: { default: 'texto' } })
    const dialog = wrapper.find('dialog').element as HTMLDialogElement
    expect(dialog.open).toBe(false)
    await wrapper.setProps({ modelValue: true })
    expect(dialog.open).toBe(true)
  })

  it('nomeia o diálogo pelo título', async () => {
    const wrapper = await mountSuspended(Modal, { props: { title: 'Reembolsar', modelValue: true } })
    const labelledBy = wrapper.find('dialog').attributes('aria-labelledby')
    expect(wrapper.find(`#${labelledBy}`).text()).toBe('Reembolsar')
  })

  it('o botão de fechar avisa quem usa o componente', async () => {
    const wrapper = await mountSuspended(Modal, { props: { title: 'Reembolsar', modelValue: true } })
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([false])
  })

  it('fecha ao clicar fora (no fundo) e mostra o rodapé quando existe', async () => {
    const wrapper = await mountSuspended(Modal, {
      props: { title: 'Reembolsar', modelValue: true },
      slots: { footer: '<button>Confirmar</button>' },
    })
    expect(wrapper.text()).toContain('Confirmar')
    await wrapper.find('dialog').trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([false])
  })
})
