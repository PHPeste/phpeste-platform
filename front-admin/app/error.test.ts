import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import ErrorPage from './error.vue'

function error(statusCode: number) {
  return { statusCode, statusMessage: 'x', message: 'x', fatal: false, unhandled: false } as never
}

describe('página de erro global', () => {
  it.each([
    [404, 'Página não encontrada', 'Ir para a visão geral'],
    [403, 'Sem permissão', 'Ir para a visão geral'],
    [500, 'Algo deu errado', 'Tentar de novo'],
  ])('%s mostra título e ação', async (code, title, action) => {
    const wrapper = await mountSuspended(ErrorPage, { props: { error: error(code) } })
    expect(wrapper.find('h1').text()).toBe(title)
    expect(wrapper.text()).toContain(`Erro ${code}`)
    expect(wrapper.text()).toContain('Voltar')
    expect(wrapper.text()).toContain(action)
  })

  it('qualquer outro código é tratado como 500', async () => {
    const wrapper = await mountSuspended(ErrorPage, { props: { error: error(502) } })
    expect(wrapper.find('h1').text()).toBe('Algo deu errado')
  })
})
