import { mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import editionService from '@/modules/shell/services/editionService'
import { useAuthStore } from '@/stores/authStore'
import { useEdition } from '@/application/shared/composables/useEdition'
import AppTopbar from './AppTopbar.vue'
import EditionSwitcher from './EditionSwitcher.vue'
import UserMenu from './UserMenu.vue'

const editions = [
  { id: 1, slug: 'ed-1', name: 'PHPeste 2027', city: 'João Pessoa', state: 'PB', year: 2027, site_url: 'https://phpeste.dev', role: 'organizador' },
  { id: 2, slug: 'ed-2', name: 'PHPeste 2026', city: 'Recife', state: 'PE', year: 2026, site_url: null, role: 'financeiro' },
]

beforeEach(async () => {
  vi.restoreAllMocks()
  useEdition().reset()
  document.cookie = 'ph_edition=; Max-Age=0; path=/'
  vi.spyOn(editionService, 'list').mockResolvedValue({ data: editions })
  await useEdition().load()
  useAuthStore().setSession({ id: 1, name: 'Nome Sobrenome', email: 'n@s.dev', avatarUrl: null }, 't')
})

describe('AppTopbar', () => {
  it('tem busca com rótulo, link do site, notificações e menu do usuário', async () => {
    const wrapper = await mountSuspended(AppTopbar)
    const input = wrapper.find('input[type="search"]')
    expect(wrapper.find(`label[for="${input.attributes('id')}"]`).text()).toBe('Buscar')
    expect(wrapper.find('form').attributes('role')).toBe('search')
    const link = wrapper.find('a[href="https://phpeste.dev"]')
    expect(link.text()).toBe('Ver site da edição')
    expect(link.attributes('rel')).toContain('noopener')
    expect(wrapper.find('button[aria-label="Notificações"]').exists()).toBe(true)
    expect(wrapper.find('button[aria-label="Menu do usuário"]').exists()).toBe(true)
  })

  it('o botão do menu (telas estreitas) avisa o layout', async () => {
    const wrapper = await mountSuspended(AppTopbar)
    await wrapper.find('button[aria-label="Abrir menu"]').trigger('click')
    expect(wrapper.emitted('open-menu')).toHaveLength(1)
  })

  it('esconde "Ver site" quando a edição não tem site', async () => {
    await useEdition().select(2)
    const wrapper = await mountSuspended(AppTopbar)
    expect(wrapper.text()).not.toContain('Ver site da edição')
  })
})

describe('EditionSwitcher', () => {
  it('mostra a edição atual e lista as outras ao abrir', async () => {
    const wrapper = await mountSuspended(EditionSwitcher)
    const trigger = wrapper.find('button[aria-label="Trocar de edição"]')
    expect(trigger.attributes('aria-expanded')).toBe('false')
    expect(trigger.text()).toContain('PHPeste 2027')
    await trigger.trigger('click')
    expect(trigger.attributes('aria-expanded')).toBe('true')
    expect(wrapper.findAll('ul button')).toHaveLength(2)
    expect(wrapper.find('[aria-current="true"]').text()).toContain('PHPeste 2027')
  })

  it('escolher outra edição troca a atual', async () => {
    const wrapper = await mountSuspended(EditionSwitcher)
    await wrapper.find('button[aria-label="Trocar de edição"]').trigger('click')
    await wrapper.findAll('ul button')[1]!.trigger('click')
    await vi.waitFor(() => expect(useEdition().current.value?.id).toBe(2))
  })

  it('Esc fecha o menu', async () => {
    const wrapper = await mountSuspended(EditionSwitcher)
    const trigger = wrapper.find('button[aria-label="Trocar de edição"]')
    await trigger.trigger('click')
    await wrapper.find('div').trigger('keydown', { key: 'Escape' })
    expect(trigger.attributes('aria-expanded')).toBe('false')
  })

  it('sem edição mostra o aviso', async () => {
    useEdition().reset()
    const wrapper = await mountSuspended(EditionSwitcher)
    await wrapper.find('button[aria-label="Trocar de edição"]').trigger('click')
    expect(wrapper.text()).toContain('Você ainda não participa de nenhuma edição.')
  })
})

describe('UserMenu', () => {
  it('mostra iniciais, nome e papel, e oferece sair', async () => {
    const wrapper = await mountSuspended(UserMenu)
    expect(wrapper.text()).toContain('NS')
    expect(wrapper.text()).toContain('Nome Sobrenome')
    expect(wrapper.text()).toContain('Organizador')
    await wrapper.find('button[aria-label="Menu do usuário"]').trigger('click')
    expect(wrapper.text()).toContain('Sair')
  })
})
