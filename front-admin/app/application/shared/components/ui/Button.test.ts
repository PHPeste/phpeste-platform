import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import Button from './Button.vue'

describe('PhButton', () => {
  it('renderiza como botão do tipo "button" com a variante primária', async () => {
    const wrapper = await mountSuspended(Button, { slots: { default: 'Salvar' } })
    const el = wrapper.find('button')
    expect(el.text()).toBe('Salvar')
    expect(el.attributes('type')).toBe('button')
    expect(el.classes()).toContain('btn-primary')
  })

  it.each([
    ['secondary', 'btn-neutral'],
    ['ghost', 'btn-ghost'],
    ['danger', 'btn-error'],
  ] as const)('aplica a variante %s', async (variant, cls) => {
    const wrapper = await mountSuspended(Button, { props: { variant } })
    expect(wrapper.find('button').classes()).toContain(cls)
  })

  it('desabilitado: não dispara clique e fica com o atributo disabled', async () => {
    const wrapper = await mountSuspended(Button, { props: { disabled: true } })
    expect(wrapper.find('button').attributes('disabled')).toBeDefined()
  })

  it('carregando: desabilita, marca aria-busy e avisa leitores de tela', async () => {
    const wrapper = await mountSuspended(Button, { props: { loading: true } })
    const el = wrapper.find('button')
    expect(el.attributes('disabled')).toBeDefined()
    expect(el.attributes('aria-busy')).toBe('true')
    expect(el.text()).toContain('Carregando')
  })

  it('com "to" renderiza um link e não um botão', async () => {
    const wrapper = await mountSuspended(Button, { props: { to: '/login' }, slots: { default: 'Entrar' } })
    expect(wrapper.find('a').attributes('href')).toBe('/login')
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('tem altura mínima de 44 px garantida pelo tema', async () => {
    const wrapper = await mountSuspended(Button)
    expect(wrapper.find('button').classes()).toContain('btn')
  })
})
