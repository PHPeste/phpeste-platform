import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import RoleMatrix from './RoleMatrix.vue'
import TeamSummary from './TeamSummary.vue'

describe('RoleMatrix', () => {
  it('monta a tabela a partir dos papéis e permissões recebidos', async () => {
    const wrapper = await mountSuspended(RoleMatrix, {
      props: {
        roles: [{ id: 'organizador', label: 'Organizador', access: '', permissions: ['edition.manage', 'cfp.review'] }, { id: 'avaliador', label: 'Revisor do CFP', access: '', permissions: ['cfp.review'] }],
        permissions: [{ id: 'edition.manage', label: 'Configurar edição e identidade' }, { id: 'cfp.review', label: 'Avaliar propostas' }],
      },
    })

    const rows = wrapper.findAll('tbody tr')
    expect(rows[0]!.text()).toContain('Configurar edição e identidade')
    expect(rows[0]!.findAll('.sr-only').map((cell) => cell.text())).toEqual(['Pode', 'Não pode'])
    expect(rows[1]!.findAll('.sr-only').map((cell) => cell.text())).toEqual(['Pode', 'Pode'])
  })
})

describe('TeamSummary', () => {
  it('mostra pessoas, convites pendentes e contagem por papel', async () => {
    const wrapper = await mountSuspended(TeamSummary, {
      props: { summary: { people: 5, pendingInvitations: 1, byRole: { organizador: 2, financeiro: 1, avaliador: 1, checkin: 1, voluntario: 0 } } },
    })

    expect(wrapper.text()).toContain('Pessoas na equipe')
    expect(wrapper.text()).toContain('1 convite pendente')
    expect(wrapper.findAll('dd').map((dd) => dd.text())).toEqual(['5', '1 convite pendente', '2', '1', '0'])
  })
})
