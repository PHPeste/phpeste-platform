import { beforeEach, describe, expect, it, vi } from 'vitest'
import sponsorsService from '../services/sponsorsService'
import { SponsorsError } from '../services/sponsorsError'
import { useSponsor } from './useSponsor'
import { cardPayload, tiersPayload } from './fixtures'

vi.mock('../services/sponsorsService', () => ({
  default: {
    sponsor: vi.fn(), tiers: vi.fn(), team: vi.fn(), updateSponsor: vi.fn(), updateSponsorship: vi.fn(), createSponsorship: vi.fn(), move: vi.fn(), recordPayment: vi.fn(),
    deletePayment: vi.fn(), logContact: vi.fn(), deleteContact: vi.fn(), upload: vi.fn(), deleteAttachment: vi.fn(), attachmentUrl: vi.fn(), createDeliverable: vi.fn(), updateDeliverable: vi.fn(), deleteDeliverable: vi.fn(),
  },
}))

const sponsorPayload = () => ({
  data: {
    id: 's-1', name: 'Cloud Sul', website: null, contact: { name: 'Joana', email: 'joana@cloud.com', phone: null }, notes: null, created_at: '2027-03-01T12:00:00Z',
    sponsorships: [
      { ...cardPayload('d-old', 'Cloud Sul', 'lost'), sponsor: { id: 's-1' }, payments: [], deliverables: [] },
      {
        ...cardPayload('d-1', 'Cloud Sul', 'closed'), sponsor: { id: 's-1' }, received: { amount: 400_000, currency: 'BRL' }, outstanding: { amount: 400_000, currency: 'BRL' },
        payments: [{ id: 'p-1', amount: { amount: 400_000, currency: 'BRL' }, paid_on: '2027-03-08', note: 'TED', recorded_by: { id: 'u-1', name: 'Rita' } }],
        deliverables: [{ id: 'e-1', sponsorship_id: 'd-1', title: 'Logo no site', owner: { id: 'u-1', name: 'Mariana Sales' }, due_on: '2027-03-01', status: 'pending', status_label: 'A fazer', overdue: true, done_at: null, position: 0 }],
      },
    ],
    contacts: [{ id: 'c-1', channel: 'meeting', channel_label: 'Reunião', happened_at: '2027-03-05T12:00:00Z', summary: 'Contrato assinado', author: { id: 'u-1', name: 'Mariana Sales' } }],
    attachments: [{ id: 'a-1', kind: 'contract', kind_label: 'Contrato', name: 'contrato.pdf', mime_type: 'application/pdf', size_bytes: 2048, uploaded_by: null, created_at: '2027-03-05T12:00:00Z' }],
  },
})

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(sponsorsService.sponsor).mockResolvedValue(sponsorPayload() as never)
  vi.mocked(sponsorsService.tiers).mockResolvedValue(tiersPayload() as never)
  vi.mocked(sponsorsService.team).mockResolvedValue({ data: { members: [] } } as never)
})

describe('useSponsor', () => {
  it('carrega a empresa e foca no acordo mais recente que não foi perdido', async () => {
    const detail = useSponsor('ed-1', 's-1')
    await detail.load()

    expect(detail.state.value).toBe('ready')
    expect(detail.deal.value).toMatchObject({ id: 'd-1', sponsorName: 'Cloud Sul', receivedCents: 400_000, outstandingCents: 400_000 })
    expect(detail.deal.value?.payments[0]).toMatchObject({ amountCents: 400_000, recordedBy: 'Rita' })
    expect(detail.deal.value?.deliverables[0]).toMatchObject({ title: 'Logo no site', overdue: true, ownerName: 'Mariana Sales' })
    expect(detail.sponsor.value?.contacts[0]).toMatchObject({ channelLabel: 'Reunião', authorName: 'Mariana Sales' })
    expect(detail.sponsor.value?.attachments[0]).toMatchObject({ name: 'contrato.pdf', kindLabel: 'Contrato' })

    detail.selectedDealId.value = 'd-old'
    expect(detail.deal.value?.id).toBe('d-old')
  })

  it('empresa de outra edição vira "não encontrada"', async () => {
    vi.mocked(sponsorsService.sponsor).mockRejectedValue(new SponsorsError('Not found', 404))
    const detail = useSponsor('ed-1', 's-x')
    await detail.load()

    expect(detail.state.value).toBe('missing')
  })

  it('registra pagamento no acordo em foco e recarrega', async () => {
    vi.mocked(sponsorsService.recordPayment).mockResolvedValue({ data: {} } as never)
    const detail = useSponsor('ed-1', 's-1')
    await detail.load()

    expect(await detail.recordPayment(400_000, '2027-03-10', ' ')).toEqual({ ok: true })
    expect(sponsorsService.recordPayment).toHaveBeenCalledWith('ed-1', 'd-1', { amount_cents: 400_000, paid_on: '2027-03-10', note: null })
    expect(sponsorsService.sponsor).toHaveBeenCalledTimes(2)
  })

  it('trocar a cota de um acordo fechado sem vaga pede confirmação', async () => {
    vi.mocked(sponsorsService.updateSponsorship).mockRejectedValueOnce(new SponsorsError('Inválido', 422, { confirm: ['A cota Ouro tem 3 vagas.'] })).mockResolvedValue({ data: {} } as never)
    const detail = useSponsor('ed-1', 's-1')
    await detail.load()

    expect(await detail.saveDeal({ tierId: 't-ouro', valueCents: 800_000, ownerId: '' })).toMatchObject({ ok: false, needsConfirm: true, message: 'A cota Ouro tem 3 vagas.' })
    expect(await detail.saveDeal({ tierId: 't-ouro', valueCents: 800_000, ownerId: '' }, true)).toEqual({ ok: true })
    expect(sponsorsService.updateSponsorship).toHaveBeenLastCalledWith('ed-1', 'd-1', { sponsor_tier_id: 't-ouro', value_cents: 800_000, owner_id: null, confirm: true })
  })

  it('pede a URL assinada só na hora de baixar', async () => {
    vi.mocked(sponsorsService.attachmentUrl).mockResolvedValueOnce({ data: { url: 'https://api/x?signature=1', expires_at: '' } } as never).mockRejectedValueOnce(new SponsorsError('x', 403))
    const detail = useSponsor('ed-1', 's-1')

    expect(await detail.attachmentUrl('a-1')).toBe('https://api/x?signature=1')
    expect(await detail.attachmentUrl('a-1')).toBeNull()
  })
})
