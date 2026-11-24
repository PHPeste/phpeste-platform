import { beforeEach, describe, expect, it, vi } from 'vitest'
import sponsorsService from '../services/sponsorsService'
import { SponsorsError } from '../services/sponsorsError'
import { useSponsorBoard } from './useSponsorBoard'
import { boardPayload, tiersPayload } from './fixtures'

vi.mock('../services/sponsorsService', () => ({
  default: { board: vi.fn(), tiers: vi.fn(), team: vi.fn(), move: vi.fn(), setGoal: vi.fn(), createSponsor: vi.fn(), createSponsorship: vi.fn(), createTier: vi.fn(), updateTier: vi.fn(), deleteTier: vi.fn() },
}))

const stages = (board: ReturnType<typeof useSponsorBoard>) => Object.fromEntries(board.columns.value.map((column) => [column.stage, column.cards.map((card) => card.sponsorName)]))

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(sponsorsService.board).mockResolvedValue(boardPayload() as never)
  vi.mocked(sponsorsService.tiers).mockResolvedValue(tiersPayload() as never)
  vi.mocked(sponsorsService.team).mockResolvedValue({ data: { members: [{ user_id: 'u-1', name: 'Mariana Sales', email: 'm@x.com' }] } } as never)
})

describe('useSponsorBoard', () => {
  it('carrega quatro colunas, os perdidos à parte, os indicadores e as cotas', async () => {
    const board = useSponsorBoard('ed-1')
    await board.load()

    expect(board.state.value).toBe('ready')
    expect(stages(board)).toEqual({ prospecting: ['Hospedagem Norte'], negotiating: [], closed: ['Cloud Sul'], paid: [] })
    expect(board.lost.value.map((deal) => deal.lostReason)).toEqual(['Sem verba'])
    expect(board.columns.value[0]!.cards[0]!.lastContact).toEqual({ summary: 'Contato feito', at: '2027-03-05T12:00:00Z' })
    expect(board.goalShare.value).toBeCloseTo(0.2417, 3)
    expect(board.openCompanies.value).toBe(1)
    expect(board.tiers.value[0]).toMatchObject({ name: 'Ouro', priceCents: 800_000, slotsTaken: 1 })
  })

  it('mover mostra o card na coluna nova antes da resposta e manda a posição no fim da coluna', async () => {
    let resolve: (value: unknown) => void = () => {}
    vi.mocked(sponsorsService.move).mockReturnValue(new Promise((done) => { resolve = done }) as never)
    const board = useSponsorBoard('ed-1')
    await board.load()

    const pending = board.move('d-1', 'closed')
    expect(stages(board).closed).toEqual(['Cloud Sul', 'Hospedagem Norte'])
    expect(sponsorsService.move).toHaveBeenCalledWith('ed-1', 'd-1', { stage: 'closed', position: 1 })

    resolve({ data: {} })
    expect(await pending).toEqual({ ok: true })
    expect(sponsorsService.board).toHaveBeenCalledTimes(2)
  })

  it('se a API recusa, o card volta para onde estava', async () => {
    vi.mocked(sponsorsService.move).mockRejectedValue(new SponsorsError('Inválido', 422, { stage: ['Faltam R$ 8.000,00 para este acordo estar pago. Registre o pagamento primeiro.'] }))
    const board = useSponsorBoard('ed-1')
    await board.load()

    const result = await board.move('d-2', 'paid')

    expect(result).toMatchObject({ ok: false, needsConfirm: false, message: 'Faltam R$ 8.000,00 para este acordo estar pago. Registre o pagamento primeiro.' })
    expect(stages(board)).toEqual({ prospecting: ['Hospedagem Norte'], negotiating: [], closed: ['Cloud Sul'], paid: [] })
  })

  it('cota sem vaga pede confirmação e a segunda chamada vai com confirm; perder manda o motivo', async () => {
    vi.mocked(sponsorsService.move).mockRejectedValueOnce(new SponsorsError('Inválido', 422, { confirm: ['A cota Ouro tem 3 vagas e 3 já estão fechadas.'] })).mockResolvedValue({ data: {} } as never)
    const board = useSponsorBoard('ed-1')
    await board.load()

    expect(await board.move('d-1', 'closed')).toMatchObject({ ok: false, needsConfirm: true })
    expect(stages(board).prospecting).toEqual(['Hospedagem Norte'])
    expect(await board.move('d-1', 'closed', { confirm: true })).toEqual({ ok: true })
    expect(sponsorsService.move).toHaveBeenLastCalledWith('ed-1', 'd-1', { stage: 'closed', position: 1, confirm: true })

    await board.move('d-2', 'lost', { lostReason: ' Sem verba ' })
    expect(sponsorsService.move).toHaveBeenLastCalledWith('ed-1', 'd-2', { stage: 'lost', position: 0, lost_reason: 'Sem verba' })
  })

  it('nova empresa cadastra a empresa e abre o acordo com a cota', async () => {
    vi.mocked(sponsorsService.createSponsor).mockResolvedValue({ data: { id: 's-9' } } as never)
    vi.mocked(sponsorsService.createSponsorship).mockResolvedValue({ data: {} } as never)
    const board = useSponsorBoard('ed-1')

    const result = await board.createSponsor({ name: ' Café do Sertão ', contactName: '', contactEmail: 'cafe@x.com', tierId: 't-ouro', valueCents: null, ownerId: 'u-1' })

    expect(result).toEqual({ ok: true, sponsorId: 's-9' })
    expect(sponsorsService.createSponsor).toHaveBeenCalledWith('ed-1', { name: 'Café do Sertão', contact_name: null, contact_email: 'cafe@x.com' })
    expect(sponsorsService.createSponsorship).toHaveBeenCalledWith('ed-1', 's-9', { sponsor_tier_id: 't-ouro', owner_id: 'u-1' })
  })
})
