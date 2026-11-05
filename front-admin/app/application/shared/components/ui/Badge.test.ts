import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import Badge from './Badge.vue'

describe('PhBadge', () => {
  it.each(['ok', 'warn', 'danger', 'info', 'neutral'] as const)('renderiza o estado %s com texto', async (status) => {
    const wrapper = await mountSuspended(Badge, { props: { status }, slots: { default: 'Pago' } })
    expect(wrapper.attributes('data-status')).toBe(status)
    expect(wrapper.text()).toBe('Pago')
  })

  it('mostra o ponto por padrão e o esconde do leitor de tela', async () => {
    const wrapper = await mountSuspended(Badge, { slots: { default: 'Pago' } })
    expect(wrapper.find('[aria-hidden="true"]').exists()).toBe(true)
  })

  it('pode ocultar o ponto', async () => {
    const wrapper = await mountSuspended(Badge, { props: { dot: false }, slots: { default: 'Pago' } })
    expect(wrapper.find('[aria-hidden="true"]').exists()).toBe(false)
  })
})
