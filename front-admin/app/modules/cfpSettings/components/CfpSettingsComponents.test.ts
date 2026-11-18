import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import CriteriaTab from './CriteriaTab.vue'
import FormPreview from './FormPreview.vue'

const form = { formats: [{ id: 'talk', label: 'Palestra de 30 min' }], levels: [{ id: 'beginner', label: 'Iniciante' }], languages: [{ id: 'pt_BR', label: 'Português' }], limits: { title: 90, summary: 3000, audience: 1000 } }
const general = { opensOn: '2027-02-01', closesOn: '2027-04-15', resultsOn: '', anonymousReview: true, requireNordesteQuota: true, nordesteQuotaPercent: 50, proposalsPerPerson: 2 }

describe('FormPreview', () => {
  it('mostra as trilhas, os limites e as regras em edição', async () => {
    const wrapper = await mountSuspended(FormPreview, { props: { general, status: 'scheduled', tracks: [{ id: 't-1', name: 'Backend e arquitetura', slots: 6, position: 1, proposals: 0 }], form } })

    const text = wrapper.text()
    expect(text).toContain('Envios até 15 de abril.')
    expect(text).toContain('Cada pessoa pode enviar até 2 propostas.')
    expect(text).toContain('A avaliação é anônima')
    expect(text).toContain('O formulário só aceita envio com o CFP aberto.')
    expect(text).toContain('até 3.000 caracteres')
    expect(wrapper.findAll('option').map((option) => option.text())).toContain('Backend e arquitetura')
  })
})

describe('CriteriaTab', () => {
  it('mostra o peso, a parte da nota e o aviso quando já há avaliações', async () => {
    const wrapper = await mountSuspended(CriteriaTab, {
      props: {
        criteria: [{ id: 'c-1', name: 'Relevância', description: 'Interessa?', weight: 3, position: 1 }, { id: 'c-2', name: 'Clareza', description: '', weight: 1, position: 2 }],
        totalWeight: 4,
        submittedReviews: 12,
        disabled: false,
        save: async () => ({ ok: true }),
        remove: async () => ({ ok: true }),
      },
    })

    expect(wrapper.findAll('li')[0]!.text()).toContain('peso 3')
    expect(wrapper.findAll('li')[0]!.text()).toContain('75% da nota')
    expect(wrapper.text()).toContain('Já existem 12 avaliações enviadas.')
  })
})
