import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import Textarea from './Textarea.vue'

describe('PhTextarea', () => {
  it('associa rótulo e campo e mostra a dica', async () => {
    const wrapper = await mountSuspended(Textarea, { props: { label: 'Mini bio', hint: 'Até 400 caracteres.' } })
    const area = wrapper.find('textarea')
    expect(wrapper.find('label').attributes('for')).toBe(area.attributes('id'))
    expect(wrapper.text()).toContain('Até 400 caracteres.')
  })

  it('erro: aria-invalid e mensagem como alerta', async () => {
    const wrapper = await mountSuspended(Textarea, { props: { label: 'Bio', error: 'Muito longo' } })
    expect(wrapper.find('textarea').attributes('aria-invalid')).toBe('true')
    expect(wrapper.find('[role="alert"]').text()).toBe('Muito longo')
  })

  it('desabilitado', async () => {
    const wrapper = await mountSuspended(Textarea, { props: { label: 'Bio', disabled: true } })
    expect(wrapper.find('textarea').attributes('disabled')).toBeDefined()
  })
})
