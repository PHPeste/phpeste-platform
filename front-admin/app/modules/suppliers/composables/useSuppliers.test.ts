import { beforeEach, describe, expect, it, vi } from 'vitest'
import suppliersService from '../services/suppliersService'
import { SuppliersError } from '../services/suppliersError'
import { useSuppliers } from './useSuppliers'
import { listPayload } from './fixtures'

vi.mock('../services/suppliersService', () => ({
  default: { list: vi.fn(), create: vi.fn(), directory: vi.fn(), link: vi.fn(), team: vi.fn() },
}))

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(suppliersService.list).mockResolvedValue(listPayload() as never)
})

describe('useSuppliers', () => {
  it('os totais da API batem com a soma das linhas, sem o recusado no orçado', async () => {
    const list = useSuppliers('ed-1')
    await list.load()

    expect(list.state.value).toBe('ready')
    expect(list.totals.value).toMatchObject({ count: 3, quotedCents: 2_760_000, contractedCents: 1_800_000 })
    expect(list.rowSums.value).toEqual({ quotedCents: list.totals.value!.quotedCents, contractedCents: list.totals.value!.contractedCents })
    expect(list.undecided.value).toBe(1)
    expect(list.suppliers.value[2]).toMatchObject({ contactsVisible: false, contacts: [] })
  })

  it('filtra por status, serviço e nome', async () => {
    const list = useSuppliers('ed-1')
    await list.search('hired', 'catering', 'mar')

    expect(suppliersService.list).toHaveBeenLastCalledWith('ed-1', { status: 'hired', service: 'catering', q: 'mar' })
    expect(list.filtered.value).toBe(true)
  })

  it('cadastra com o primeiro contato e volta o id; erro de nome repetido vira mensagem do campo', async () => {
    vi.mocked(suppliersService.create).mockResolvedValueOnce({ data: { id: 'l-9' } } as never)
    const list = useSuppliers('ed-1')
    const input = { name: ' Som e Luz JP ', service: 'audio_visual' as const, description: '', document: '12.345.678/0001-90', website: '', city: '', state: 'pb', ownerId: '', contactName: 'Caio', contactPhone: '(83) 9', contactEmail: '' }

    expect(await list.create(input)).toEqual({ ok: true, linkId: 'l-9' })
    expect(suppliersService.create).toHaveBeenCalledWith('ed-1', expect.objectContaining({
      name: 'Som e Luz JP', document: '12.345.678/0001-90', state: 'PB', owner_id: null, contacts: [{ name: 'Caio', role: null, email: null, phone: '(83) 9' }],
    }))

    vi.mocked(suppliersService.create).mockRejectedValueOnce(new SuppliersError('x', 422, { name: ['Já existe um fornecedor com esse nome na comunidade.'] }))
    expect(await list.create(input)).toMatchObject({ ok: false, fieldErrors: { name: 'Já existe um fornecedor com esse nome na comunidade.' } })
  })

  it('busca no cadastro da comunidade e traz para a edição', async () => {
    vi.mocked(suppliersService.directory).mockResolvedValue({ data: [{ id: 's-1', name: 'Espaço Mar Azul', service: 'venue', service_label: 'Local', description: null, document: null, website: null, city: null, state: null, contacts_visible: false, contacts: null, in_this_edition: false, editions: [{ id: 'ed-26', name: 'PHPeste 2026', status: 'hired', status_label: 'Contratado' }] }] } as never)
    vi.mocked(suppliersService.link).mockResolvedValue({ data: { id: 'l-7' } } as never)
    const list = useSuppliers('ed-1')

    await list.searchDirectory('mar')
    expect(list.directory.value[0]).toMatchObject({ name: 'Espaço Mar Azul', inThisEdition: false, editions: [{ name: 'PHPeste 2026', statusLabel: 'Contratado' }] })
    expect(await list.reuse('s-1', '')).toEqual({ ok: true, linkId: 'l-7' })
    expect(suppliersService.link).toHaveBeenCalledWith('ed-1', 's-1', null)
  })
})
