import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import FormErrors from './FormErrors.vue'
import LoadError from './LoadError.vue'
import PageSkeleton from './PageSkeleton.vue'

describe('PhLoadError', () => {
  it('diz que nada foi perdido e oferece tentar de novo', async () => {
    const wrapper = await mountSuspended(LoadError, { props: { title: 'Não foi possível carregar os pedidos' } })

    expect(wrapper.attributes('role')).toBe('alert')
    expect(wrapper.text()).toContain('Sua conexão pode ter caído. Nada foi perdido.')
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('retry')).toHaveLength(1)
  })
})

describe('PhPageSkeleton', () => {
  it('anuncia o carregamento e desenha indicadores e cartões, sem rodinha', async () => {
    const wrapper = await mountSuspended(PageSkeleton, { props: { kpis: 4, cards: 2, lines: 3 } })

    expect(wrapper.attributes('aria-busy')).toBe('true')
    expect(wrapper.text()).toContain('Carregando')
    expect(wrapper.findAll('.card')).toHaveLength(6)
    expect(wrapper.find('.loading-spinner').exists()).toBe(false)
  })
})

describe('PhFormErrors', () => {
  it('resume quantos campos precisam de ajuste e leva até cada um', async () => {
    document.body.innerHTML = '<input id="f-email">'
    const wrapper = await mountSuspended(FormErrors, {
      props: { errors: { email: 'Informe um e-mail completo.', name: 'Obrigatório.', city: null }, labels: { email: 'E-mail', name: 'Nome' }, fieldIds: { email: 'f-email' } },
      attachTo: document.body,
    })

    expect(wrapper.find('b').text()).toBe('2 campos precisam de ajuste')
    expect(wrapper.findAll('li').map((item) => item.text())).toEqual(['E-mail: Informe um e-mail completo.', 'Nome: Obrigatório.'])
    await wrapper.find('a').trigger('click')
    expect(document.activeElement?.id).toBe('f-email')
  })

  it('some sem erros', async () => {
    const wrapper = await mountSuspended(FormErrors, { props: { errors: {}, labels: {} } })
    expect(wrapper.find('[data-testid="form-errors"]').exists()).toBe(false)
  })
})
