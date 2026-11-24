import { beforeEach, describe, expect, it, vi } from 'vitest'
import suppliersService from '../services/suppliersService'
import { SuppliersError } from '../services/suppliersError'
import { useSupplier } from './useSupplier'
import { linkPayload } from './fixtures'

vi.mock('../services/suppliersService', () => ({
  default: {
    show: vi.fn(), team: vi.fn(), update: vi.fn(), remove: vi.fn(), changeStatus: vi.fn(), logActivity: vi.fn(), addContact: vi.fn(), deleteContact: vi.fn(),
    recordQuote: vi.fn(), rejectQuote: vi.fn(), deleteQuote: vi.fn(), upload: vi.fn(), attachmentUrl: vi.fn(), deleteAttachment: vi.fn(),
  },
}))

const detailPayload = () => ({
  data: linkPayload('l-2', 'Buffet Sabor da Terra', 'quoting', {
    quotes: [
      { id: 'q-1', amount: { amount: 960_000, currency: 'BRL' }, description: 'Coffee break + almoço, 2 dias', received_on: '2027-03-01', valid_until: '2027-07-30', expired: false, status: 'received', status_label: 'Recebido', recorded_by: { id: 'u-1', name: 'Rita' } },
    ],
    attachments: [],
    activities: [{ id: 'a-1', kind: 'quote', channel: null, channel_label: null, summary: 'Orçamento de R$ 9.600,00 recebido.', author: { id: 'u-1', name: 'Rita' }, happened_at: '2027-03-01T12:00:00Z' }],
  }),
})

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(suppliersService.show).mockResolvedValue(detailPayload() as never)
  vi.mocked(suppliersService.team).mockResolvedValue({ data: { members: [] } } as never)
})

describe('useSupplier', () => {
  it('carrega orçamentos e histórico', async () => {
    const detail = useSupplier('ed-1', 'l-2')
    await detail.load()

    expect(detail.state.value).toBe('ready')
    expect(detail.supplier.value?.quotes[0]).toMatchObject({ amountCents: 960_000, recordedBy: 'Rita', validUntil: '2027-07-30' })
    expect(detail.supplier.value?.activities[0]).toMatchObject({ kind: 'quote', channelLabel: null })
  })

  it('fornecedor de outra comunidade vira "não encontrado"', async () => {
    vi.mocked(suppliersService.show).mockRejectedValue(new SuppliersError('Not found', 404))
    const detail = useSupplier('ed-1', 'l-x')
    await detail.load()

    expect(detail.state.value).toBe('missing')
  })

  it('contrata com o orçamento escolhido ou com o valor, e recusa com motivo', async () => {
    vi.mocked(suppliersService.changeStatus).mockResolvedValue({ data: {} } as never)
    const detail = useSupplier('ed-1', 'l-2')

    await detail.hire('q-1', null)
    expect(suppliersService.changeStatus).toHaveBeenLastCalledWith('ed-1', 'l-2', { status: 'hired', quote_id: 'q-1' })
    await detail.hire(null, 500_000)
    expect(suppliersService.changeStatus).toHaveBeenLastCalledWith('ed-1', 'l-2', { status: 'hired', contracted_cents: 500_000 })
    await detail.decline('  Acima do orçamento ')
    expect(suppliersService.changeStatus).toHaveBeenLastCalledWith('ed-1', 'l-2', { status: 'declined', reason: 'Acima do orçamento' })
  })

  it('erro de regra vira mensagem: orçamento aceito não sai', async () => {
    vi.mocked(suppliersService.deleteQuote).mockRejectedValue(new SuppliersError('x', 422, { quote: ['Este é o orçamento aceito do contrato. Mude o status do fornecedor antes.'] }))
    const detail = useSupplier('ed-1', 'l-2')

    expect(await detail.deleteQuote('q-1')).toMatchObject({ ok: false, message: 'Este é o orçamento aceito do contrato. Mude o status do fornecedor antes.' })
  })

  it('registra proposta com a validade em branco como nula', async () => {
    vi.mocked(suppliersService.recordQuote).mockResolvedValue({ data: {} } as never)
    const detail = useSupplier('ed-1', 'l-2')

    expect(await detail.recordQuote({ amountCents: 420_000, description: ' Só coffee break ', receivedOn: '2027-03-10', validUntil: '' })).toEqual({ ok: true })
    expect(suppliersService.recordQuote).toHaveBeenCalledWith('ed-1', 'l-2', { amount_cents: 420_000, description: 'Só coffee break', received_on: '2027-03-10', valid_until: null })
  })
})
