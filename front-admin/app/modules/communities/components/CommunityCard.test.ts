import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import CommunityCard from './CommunityCard.vue'
import type { CommunitySummary } from '../types'

const community: CommunitySummary = {
  id: 'c1', name: 'PHPeste', slug: 'phpeste', initials: 'PH', canManage: true, roleLabel: 'Organizador',
  editions: [
    { id: 'ed-27', name: 'PHPeste 2027', slug: '2027', status: 'cfp_open', statusLabel: 'CFP aberto', statusTone: 'warn', dates: '8 e 9 out 2027', place: 'João Pessoa (PB)', isClosed: false, role: 'organizador' },
    { id: 'ed-26', name: 'PHPeste 2026', slug: '2026', status: 'closed', statusLabel: 'Encerrada', statusTone: 'neutral', dates: 'Data a definir', place: 'Recife (PE)', isClosed: true, role: 'organizador' },
  ],
}

describe('CommunityCard', () => {
  it('mostra etapa, datas e lugar, com "Ver histórico" para a encerrada', async () => {
    const wrapper = await mountSuspended(CommunityCard, { props: { community } })

    expect(wrapper.text()).toContain('phpeste · Organizador')
    expect(wrapper.text()).toContain('8 e 9 out 2027 · João Pessoa (PB)')
    expect(wrapper.text()).toContain('CFP aberto')
    const buttons = wrapper.findAll('li button')
    expect(buttons[0]!.text()).toContain('Abrir')
    expect(buttons[1]!.text()).toContain('Ver histórico')

    await buttons[1]!.trigger('click')
    expect(wrapper.emitted('open')).toEqual([['ed-26']])
  })

  it('oferece "Nova edição" só para quem gerencia a comunidade', async () => {
    const managed = await mountSuspended(CommunityCard, { props: { community } })
    expect(managed.text()).toContain('Nova edição')

    const reviewer = await mountSuspended(CommunityCard, { props: { community: { ...community, canManage: false, roleLabel: 'Avaliador' } } })
    expect(reviewer.text()).not.toContain('Nova edição')
  })
})
