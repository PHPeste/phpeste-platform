import { beforeEach, describe, expect, it, vi } from 'vitest'
import editionService from '@/modules/shell/services/editionService'
import { useEdition } from './useEdition'

const payload = [
  { id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHPeste', slug: 'phpeste' }, status: 'cfp_open', status_label: 'CFP aberto', starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB', year: 2027, site_url: 'https://phpeste.dev', role: 'organizador' },
  { id: 'ed-26', slug: '2026', name: 'PHPeste 2026', community_id: 'c1', status: 'closed', status_label: 'Encerrada', starts_on: null, ends_on: null, city: 'Recife', state: 'PE', year: 2026, site_url: null, role: 'financeiro' },
  { id: 'ed-x', slug: 'x', name: 'Papel inválido', community_id: 'c1', status: 'draft', status_label: 'Rascunho', starts_on: null, ends_on: null, city: 'X', state: 'XX', year: 2025, site_url: null, role: 'hacker' },
]

beforeEach(() => {
  vi.restoreAllMocks()
  useEdition().clear()
})

describe('useEdition', () => {
  it('começa sem edição atual', () => {
    expect(useEdition().current.value).toBeNull()
  })

  it('guarda e limpa a edição atual', () => {
    const { current, setCurrent, clear } = useEdition()

    setCurrent({ id: 'ed-27', slug: 'phpeste-2027', name: 'PHPeste 2027' })
    expect(current.value).toEqual({ id: 'ed-27', slug: 'phpeste-2027', name: 'PHPeste 2027' })

    clear()
    expect(current.value).toBeNull()
  })

  it('carrega, traduz e ignora papéis desconhecidos', async () => {
    vi.spyOn(editionService, 'list').mockResolvedValue({ data: payload })
    const edition = useEdition()
    await edition.load()
    expect(edition.status.value).toBe('ready')
    expect(edition.editions.value.map((e) => e.id)).toEqual(['ed-27', 'ed-26'])
    expect(edition.current.value?.yearBadge).toBe('27')
    expect(edition.role.value).toBe('organizador')
  })

  it('troca de edição, muda o papel e recarrega os dados', async () => {
    vi.spyOn(editionService, 'list').mockResolvedValue({ data: payload })
    const edition = useEdition()
    await edition.load()
    await edition.select('ed-26')
    expect(edition.current.value?.id).toBe('ed-26')
    expect(edition.role.value).toBe('financeiro')
    expect(edition.isReadOnly.value).toBe(true)
  })

  it('ignora troca para edição inexistente', async () => {
    vi.spyOn(editionService, 'list').mockResolvedValue({ data: payload })
    const edition = useEdition()
    await edition.load()
    await edition.select('nada')
    expect(edition.current.value?.id).toBe('ed-27')
  })

  it('marca erro quando a API falha e não fica com papel', async () => {
    vi.spyOn(editionService, 'list').mockRejectedValue(new Error('offline'))
    const edition = useEdition()
    await edition.load()
    expect(edition.status.value).toBe('error')
    expect(edition.role.value).toBeNull()
  })

  it('ensureLoaded só busca uma vez', async () => {
    const spy = vi.spyOn(editionService, 'list').mockResolvedValue({ data: payload })
    const edition = useEdition()
    await edition.ensureLoaded()
    await edition.ensureLoaded()
    expect(spy).toHaveBeenCalledTimes(1)
  })
})
