import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import ProposalView from './ProposalView.vue'
import ScoreForm from './ScoreForm.vue'

const proposal = { id: 'p-1', number: 32, title: 'Octane em produção', track: 'Backend e arquitetura', format: 'Palestra de 30 min', durationMinutes: 30, level: 'Intermediário', language: 'pt_BR', summary: 'Seis meses com Octane.', audience: 'Quem usa Laravel.', nordeste: true }

describe('ProposalView', () => {
  it('mostra "Palestrante oculto" e nenhum nome quando a avaliação é anônima', async () => {
    const wrapper = await mountSuspended(ProposalView, { props: { proposal: { ...proposal, speakers: null } } })

    expect(wrapper.text()).toContain('Proposta #32 · Backend e arquitetura · 30 min')
    expect(wrapper.text()).toContain('Palestrante oculto')
    expect(wrapper.text()).not.toContain('Palestrantes:')
  })

  it('mostra os palestrantes para a organização', async () => {
    const wrapper = await mountSuspended(ProposalView, { props: { proposal: { ...proposal, speakers: ['Ana Lima'] } } })
    expect(wrapper.text()).toContain('Palestrantes: Ana Lima')
  })
})

describe('ScoreForm', () => {
  it('mostra o peso, marca a nota e destaca o critério ativo', async () => {
    const wrapper = await mountSuspended(ScoreForm, {
      props: { criteria: [{ id: 'c-1', name: 'Relevância', description: null, weight: 3 }, { id: 'c-2', name: 'Clareza', description: null, weight: 2 }], scores: { 'c-1': 4 }, active: 1, busy: null, error: null, comment: '' },
    })

    expect(wrapper.text()).toContain('peso 3')
    expect(wrapper.find('input[aria-label="Nota 4 para Relevância"]').element).toHaveProperty('checked', true)
    expect(wrapper.findAll('fieldset')[1]!.attributes('data-active')).toBe('true')

    await wrapper.find('input[aria-label="Nota 2 para Clareza"]').trigger('change')
    expect(wrapper.emitted('score')?.[0]).toEqual(['c-2', 2])
  })
})
