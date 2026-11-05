import { mountSuspended, mockNuxtImport } from '@nuxt/test-utils/runtime'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import editionService from '@/modules/shell/services/editionService'
import { useEdition } from '@/application/shared/composables/useEdition'
import SideNav from './SideNav.vue'

const { path } = vi.hoisted(() => ({ path: { value: '/' } }))
mockNuxtImport('useRoute', () => () => ({ path: path.value }))

async function asRole(role: string) {
  vi.spyOn(editionService, 'list').mockResolvedValue({
    data: [{ id: 1, slug: 'ed-1', name: 'PHPeste 2027', city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role }],
  })
  await useEdition().load()
}

beforeEach(() => {
  vi.restoreAllMocks()
  path.value = '/'
  useEdition().reset()
})

describe('SideNav', () => {
  it('organizador vê as 5 seções e os 17 itens, num <nav> nomeado', async () => {
    await asRole('organizador')
    const wrapper = await mountSuspended(SideNav)
    expect(wrapper.find('nav').attributes('aria-label')).toBe('Principal')
    expect(wrapper.findAll('nav h2').map((h) => h.text())).toEqual(['Edição', 'Conteúdo', 'Vendas', 'Organização', 'Sistema'])
    expect(wrapper.findAll('nav a')).toHaveLength(17)
  })

  it('esconde o que o papel não permite (e seções vazias)', async () => {
    await asRole('financeiro')
    const wrapper = await mountSuspended(SideNav)
    const labels = wrapper.findAll('nav a').map((a) => a.text())
    expect(labels).toEqual(['Visão geral', 'Ingressos', 'Pedidos', 'Pagamentos', 'Finanças'])
    expect(wrapper.text()).not.toContain('Configurar CFP')
    expect(wrapper.findAll('nav h2').map((h) => h.text())).toEqual(['Edição', 'Vendas', 'Organização'])
  })

  it('marca só o item ativo com aria-current, o mais específico', async () => {
    await asRole('organizador')
    path.value = '/cfp/avaliacao'
    const wrapper = await mountSuspended(SideNav)
    const current = wrapper.findAll('[aria-current="page"]')
    expect(current).toHaveLength(1)
    expect(current[0]!.text()).toBe('Avaliar propostas')
  })

  it('mostra a edição ativa no rodapé', async () => {
    await asRole('organizador')
    const wrapper = await mountSuspended(SideNav)
    expect(wrapper.text()).toContain('PHPeste 2027 · João Pessoa')
  })

  it('avisa a gaveta para fechar ao navegar', async () => {
    await asRole('organizador')
    const wrapper = await mountSuspended(SideNav)
    await wrapper.find('nav a').trigger('click')
    expect(wrapper.emitted('navigate')).toHaveLength(1)
  })
})
