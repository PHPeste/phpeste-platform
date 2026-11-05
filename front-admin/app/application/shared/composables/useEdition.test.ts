import { beforeEach, describe, expect, it, vi } from 'vitest'
import editionService from '@/modules/shell/services/editionService'
import { useEdition } from './useEdition'

const payload = [
  { id: 1, slug: 'phpeste-2027', name: 'PHPeste 2027', city: 'João Pessoa', state: 'PB', year: 2027, site_url: 'https://phpeste.dev', role: 'organizador' },
  { id: 2, slug: 'phpeste-2026', name: 'PHPeste 2026', city: 'Recife', state: 'PE', year: 2026, site_url: null, role: 'financeiro' },
  { id: 3, slug: 'x', name: 'Papel inválido', city: 'X', state: 'XX', year: 2025, site_url: null, role: 'hacker' },
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

    setCurrent({ id: 1, slug: 'phpeste-2027', name: 'PHPeste 2027' })
    expect(current.value).toEqual({ id: 1, slug: 'phpeste-2027', name: 'PHPeste 2027' })

    clear()
    expect(current.value).toBeNull()
  })

  it('carrega, traduz e ignora papéis desconhecidos', async () => {
    vi.spyOn(editionService, 'list').mockResolvedValue({ data: payload })
    const edition = useEdition()
    await edition.load()
    expect(edition.status.value).toBe('ready')
    expect(edition.editions.value.map((e) => e.id)).toEqual([1, 2])
    expect(edition.current.value?.yearBadge).toBe('27')
    expect(edition.role.value).toBe('organizador')
  })

  it('troca de edição, muda o papel e recarrega os dados', async () => {
    vi.spyOn(editionService, 'list').mockResolvedValue({ data: payload })
    const edition = useEdition()
    await edition.load()
    await edition.select(2)
    expect(edition.current.value?.id).toBe(2)
    expect(edition.role.value).toBe('financeiro')
  })

  it('ignora troca para edição inexistente', async () => {
    vi.spyOn(editionService, 'list').mockResolvedValue({ data: payload })
    const edition = useEdition()
    await edition.load()
    await edition.select(99)
    expect(edition.current.value?.id).toBe(1)
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
