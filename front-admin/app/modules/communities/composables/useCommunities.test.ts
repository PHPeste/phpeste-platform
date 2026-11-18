import { beforeEach, describe, expect, it, vi } from 'vitest'
import communitiesService from '../services/communitiesService'
import { groupCommunities, useCommunities } from './useCommunities'

vi.mock('../services/communitiesService', () => ({ default: { listEditions: vi.fn(), listManagedCommunities: vi.fn() } }))

const edition = (overrides: Record<string, unknown>) => ({
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c-phpeste', community: { id: 'c-phpeste', name: 'PHPeste', slug: 'phpeste' },
  status: 'cfp_open', status_label: 'CFP aberto', starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB',
  year: 2027, site_url: null, role: 'organizador', ...overrides,
})

beforeEach(() => vi.clearAllMocks())

describe('groupCommunities', () => {
  it('agrupa as edições por comunidade, com etapa, datas, lugar e papel', () => {
    const communities = groupCommunities(
      [
        edition({}),
        edition({ id: 'ed-26', name: 'PHPeste 2026', status: 'closed', status_label: 'Encerrada', city: 'Recife', state: 'PE', starts_on: null, ends_on: null }),
        edition({ id: 'ed-pinga', name: 'PHPinga 2027', community_id: 'c-pb', community: { id: 'c-pb', name: 'PHP-PB', slug: 'php-pb' }, status: 'draft', status_label: 'Rascunho', role: 'avaliador' }),
      ],
      [{ id: 'c-phpeste', name: 'PHPeste', slug: 'phpeste', created_at: '' }],
    )

    expect(communities).toHaveLength(2)
    expect(communities[0]).toMatchObject({ name: 'PHPeste', canManage: true, roleLabel: 'Organizador' })
    expect(communities[0]!.editions[0]).toMatchObject({ dates: '8 e 9 out 2027', place: 'João Pessoa (PB)', statusTone: 'warn', isClosed: false })
    expect(communities[0]!.editions[1]).toMatchObject({ dates: 'Data a definir', isClosed: true })
    expect(communities[1]).toMatchObject({ name: 'PHP-PB', canManage: false, roleLabel: 'Avaliador', initials: 'PB' })
  })

  it('mostra a comunidade que a pessoa criou mesmo sem nenhuma edição ainda', () => {
    expect(groupCommunities([], [{ id: 'c-nova', name: 'PHP-SE', slug: 'php-se', created_at: '' }])).toEqual([
      expect.objectContaining({ name: 'PHP-SE', canManage: true, editions: [] }),
    ])
  })
})

describe('useCommunities', () => {
  it('fica vazio quando a pessoa não participa de nada', async () => {
    vi.mocked(communitiesService.listEditions).mockResolvedValueOnce({ data: [] })
    vi.mocked(communitiesService.listManagedCommunities).mockResolvedValueOnce({ data: [] })

    const { load, isEmpty, state } = useCommunities()
    await load()

    expect(state.value).toBe('ready')
    expect(isEmpty.value).toBe(true)
  })

  it('marca erro quando a API falha', async () => {
    vi.mocked(communitiesService.listEditions).mockRejectedValueOnce(new Error('offline'))
    vi.mocked(communitiesService.listManagedCommunities).mockResolvedValueOnce({ data: [] })

    const { load, state } = useCommunities()
    await load()

    expect(state.value).toBe('error')
  })
})
