import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import MobileMenu from './MobileMenu.vue'

describe('MobileMenu', () => {
  it('abre e fecha com v-model e tem botão de fechar nomeado', async () => {
    const wrapper = await mountSuspended(MobileMenu, { props: { modelValue: false } })
    const dialog = wrapper.find('dialog')
    expect((dialog.element as HTMLDialogElement).open).toBe(false)
    await wrapper.setProps({ modelValue: true })
    expect((dialog.element as HTMLDialogElement).open).toBe(true)
    await wrapper.find('button[aria-label="Fechar menu"]').trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([false])
  })

  it('só aparece em telas estreitas', async () => {
    const wrapper = await mountSuspended(MobileMenu, { props: { modelValue: false } })
    expect(wrapper.find('dialog').classes()).toContain('lg:hidden')
  })
})
