import { beforeEach, describe, expect, it, vi } from 'vitest'
import teamService from '../services/teamService'
import { TeamError } from '../services/teamError'
import { useTeam } from './useTeam'

vi.mock('../services/teamService', () => ({
  default: { list: vi.fn(), roles: vi.fn(), invite: vi.fn(), resendInvitation: vi.fn(), cancelInvitation: vi.fn(), changeRole: vi.fn(), removeMember: vi.fn() },
}))

const team = {
  data: {
    members: [{ id: 'm-1', user_id: 'u-1', name: 'Mariana Sales', email: 'mariana@x.dev', role: 'organizador', role_label: 'Organizador', access: 'Tudo na edição', last_seen_at: null, joined_at: '2027-01-01T00:00:00Z' }],
    invitations: [{ id: 'inv-1', email: 'thiago@x.dev', name: 'Thiago Braga', role: 'checkin', role_label: 'Credenciamento', expires_at: '2027-03-17T00:00:00Z', created_at: '2027-03-10T00:00:00Z' }],
    summary: { people: 1, pending_invitations: 1, by_role: { organizador: 1, avaliador: 0 } },
  },
}
const roles = {
  data: {
    roles: [{ id: 'organizador', label: 'Organizador', access: 'Tudo na edição', permissions: ['edition.manage'] }, { id: 'avaliador', label: 'Revisor do CFP', access: 'CFP e palestrantes', permissions: ['cfp.review'] }],
    permissions: [{ id: 'edition.manage', label: 'Configurar edição e identidade' }, { id: 'cfp.review', label: 'Avaliar propostas' }],
  },
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(teamService.list).mockResolvedValue(team)
  vi.mocked(teamService.roles).mockResolvedValue(roles)
})

describe('useTeam', () => {
  it('carrega equipe, convites, resumo e a tabela de papéis da API', async () => {
    const result = useTeam('ed-1')
    await result.load()

    expect(result.state.value).toBe('ready')
    expect(result.members.value[0]).toMatchObject({ name: 'Mariana Sales', initials: 'MS', role: 'organizador', lastSeen: 'nunca' })
    expect(result.invitations.value[0]).toMatchObject({ email: 'thiago@x.dev', initials: 'TB', roleLabel: 'Credenciamento' })
    expect(result.summary.value?.byRole).toEqual({ organizador: 1, financeiro: 0, avaliador: 0, checkin: 0, voluntario: 0 })
    expect(result.permissions.value).toHaveLength(2)
    expect(result.roleOptions.value[1]).toEqual({ value: 'avaliador', label: 'Revisor do CFP · CFP e palestrantes' })
  })

  it('recarrega a equipe depois de uma alteração', async () => {
    vi.mocked(teamService.invite).mockResolvedValueOnce({ data: team.data.invitations[0]! })
    const result = useTeam('ed-1')
    await result.load()

    expect(await result.invite({ email: 'novo@x.dev', name: '', role: 'avaliador' })).toBeNull()
    expect(teamService.invite).toHaveBeenCalledWith('ed-1', { email: 'novo@x.dev', name: '', role: 'avaliador' })
    expect(teamService.list).toHaveBeenCalledTimes(2)
  })

  it('devolve a mensagem do backend quando ele recusa remover o último organizador', async () => {
    vi.mocked(teamService.removeMember).mockRejectedValueOnce(new TeamError('x', 422, { role: ['A edição precisa de pelo menos uma pessoa organizadora.'] }))
    const result = useTeam('ed-1')
    await result.load()

    expect(await result.remove(result.members.value[0]!)).toBe('A edição precisa de pelo menos uma pessoa organizadora.')
  })

  it('mostra erro de carregamento quando a API falha', async () => {
    vi.mocked(teamService.roles).mockRejectedValueOnce(new TeamError('x', 500))
    const result = useTeam('ed-1')
    await result.load()

    expect(result.state.value).toBe('error')
  })
})
