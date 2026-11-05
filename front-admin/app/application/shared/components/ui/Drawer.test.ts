import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import Drawer from './Drawer.vue'

describe('PhDrawer', () => {
  it('abre e fecha com v-model e fica ancorado à direita', async () => {
    const wrapper = await mountSuspended(Drawer, { props: { title: 'Editar sessão', modelValue: false } })
    const dialog = wrapper.find('dialog')
    expect(dialog.classes()).toContain('modal-end')
    await wrapper.setProps({ modelValue: true })
    expect((dialog.element as HTMLDialogElement).open).toBe(true)
  })

  it('tem título como nome acessível e botão de fechar', async () => {
    const wrapper = await mountSuspended(Drawer, { props: { title: 'Editar sessão', modelValue: true } })
    const labelledBy = wrapper.find('dialog').attributes('aria-labelledby')
    expect(wrapper.find(`#${labelledBy}`).text()).toBe('Editar sessão')
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([false])
  })
})
