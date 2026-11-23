import { beforeEach, describe, expect, it, vi } from 'vitest'
import attendeesService from '../services/attendeesService'
import { AttendeesError } from '../services/attendeesError'
import { useAttendees } from './useAttendees'

vi.mock('../services/attendeesService', () => ({
  default: { list: vi.fn(), show: vi.fn(), update: vi.fn(), resend: vi.fn(), reminderPreview: vi.fn(), remind: vi.fn(), badges: vi.fn() },
}))

const ticket = (overrides: Record<string, unknown> = {}) => ({
  id: 't-1', code: 'K7QM-4XTD', status: 'active', status_label: 'Válido', order_id: 'o-1', order_number: 42, ticket_type_id: 'tt-1', ticket_type_name: 'Geral', batch_name: '1º lote',
  holder: { name: 'Carla Dias', email: 'carla@exemplo.com', badge_name: null, company: null, tshirt_size: 'M', accessibility: 'Intérprete de Libras' },
  missing: ['badge_name'], transferred_from: 'Bia Lima', issued_at: null, cancelled_at: null, last_sent_at: null,
  ...overrides,
})
const summary = { participants: 2, complete: 1, missing: 1, accessibility: 1, transferred: 1 }

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(attendeesService.list).mockResolvedValue({ data: [ticket()], meta: { current_page: 1, last_page: 2, total: 51, summary } } as never)
  vi.mocked(attendeesService.show).mockResolvedValue({ data: ticket({
    transfers: [{ from: { name: 'Bia Lima', email: 'bia@exemplo.com' }, to: { name: 'Carla Dias', email: 'carla@exemplo.com' }, channel: 'organizer', transferred_by: 'u-1', at: '2027-06-10T12:00:00Z' }],
    edits: [
      { id: 'e-1', changes: [{ field: 'badge_name', label: 'Nome no crachá', from: null, to: 'Carla' }], actor: null, at: '2027-06-10T12:00:00Z' },
      { id: 'e-2', changes: [{ field: 'badge_name', label: 'Nome no crachá', from: 'Carla', to: 'Carlinha' }], actor: { id: 'u-1', name: 'Rita' }, at: '2027-06-11T12:00:00Z' },
    ],
  }) } as never)
})

describe('useAttendees', () => {
  it('carrega a página com o resumo e traduz o participante', async () => {
    const attendees = useAttendees('ed-1', { private: true })
    await attendees.load()

    expect(attendees.state.value).toBe('ready')
    expect(attendees.total.value).toBe(51)
    expect(attendees.summary.value).toEqual(summary)
    expect(attendees.attendees.value[0]).toMatchObject({ name: 'Carla Dias', ticketTypeName: 'Geral', missing: ['badge_name'], transferredFrom: 'Bia Lima', accessibility: 'Intérprete de Libras' })
  })

  it('sem acessibilidade na resposta, o campo fica indefinido (financeiro)', async () => {
    vi.mocked(attendeesService.list).mockResolvedValue({ data: [ticket({ holder: { name: 'Ana', email: 'a@x.com', badge_name: 'Ana', company: null, tshirt_size: 'M' } })], meta: { current_page: 1, last_page: 1, total: 1 } } as never)
    const attendees = useAttendees('ed-1', { private: false })
    await attendees.load()

    expect(attendees.attendees.value[0]!.accessibility).toBeUndefined()
    expect(attendees.summary.value).toBeNull()
  })

  it('filtrar volta para a primeira página', async () => {
    const attendees = useAttendees('ed-1', { private: true })
    await attendees.goTo(2)
    await attendees.search('transferred', 'carla')

    expect(attendeesService.list).toHaveBeenLastCalledWith('ed-1', { page: 1, filter: 'transferred', q: 'carla' })
    expect(attendees.filtered.value).toBe(true)
  })

  it('abre com transferências e alterações, a mais recente primeiro', async () => {
    const attendees = useAttendees('ed-1', { private: true })
    await attendees.open('t-1')

    expect(attendees.detailState.value).toBe('ready')
    expect(attendees.detail.value?.transfers[0]).toMatchObject({ fromName: 'Bia Lima', toName: 'Carla Dias', byOrganizer: true })
    expect(attendees.detail.value?.edits.map((edit) => edit.actorName)).toEqual(['Rita', null])
  })

  it('salva limpando espaços e mandando vazio como nulo; acessibilidade só para a organização', async () => {
    vi.mocked(attendeesService.update).mockResolvedValue({ data: ticket({ holder: { name: 'Carla Dias', email: 'carla@exemplo.com', badge_name: 'Carla', company: null, tshirt_size: 'M', accessibility: null }, missing: [] }) } as never)
    const attendees = useAttendees('ed-1', { private: true })
    await attendees.load()
    await attendees.open('t-1')
    const result = await attendees.save({ name: ' Carla Dias ', badgeName: ' Carla ', company: '  ', tshirtSize: 'M', accessibility: '' })

    expect(result).toEqual({ ok: true })
    expect(attendeesService.update).toHaveBeenCalledWith('ed-1', 't-1', { holder_name: 'Carla Dias', badge_name: 'Carla', company: null, tshirt_size: 'M', accessibility: null })
    expect(attendees.attendees.value[0]).toMatchObject({ badgeName: 'Carla', missing: [], transferredFrom: 'Bia Lima' })

    const finance = useAttendees('ed-1', { private: false })
    await finance.open('t-1')
    await finance.save({ name: 'Carla', badgeName: '', company: '', tshirtSize: '', accessibility: 'x' })
    expect(vi.mocked(attendeesService.update).mock.calls[1]![2]).not.toHaveProperty('accessibility')
  })

  it('traduz o erro de validação para o campo do formulário', async () => {
    vi.mocked(attendeesService.update).mockRejectedValue(new AttendeesError('Inválido', 422, { badge_name: ['Use até 60 caracteres.'] }))
    const attendees = useAttendees('ed-1', { private: true })
    await attendees.open('t-1')

    expect(await attendees.save({ name: 'Carla', badgeName: 'x'.repeat(61), company: '', tshirtSize: '', accessibility: '' }))
      .toEqual({ ok: false, message: 'Use até 60 caracteres.', fieldErrors: { badgeName: 'Use até 60 caracteres.' } })
  })

  it('prévia e envio da cobrança de dados', async () => {
    vi.mocked(attendeesService.reminderPreview).mockResolvedValue({ data: { recipients: 3, email: { subject: 'Complete seus dados', html: '<p>Oi</p>', text: 'Oi' } } } as never)
    vi.mocked(attendeesService.remind).mockRejectedValue(new AttendeesError('Inválido', 422, { confirm: ['Confirme a cobrança por e-mail para 4 pessoas.'] }))
    const attendees = useAttendees('ed-1', { private: true })

    expect(await attendees.reminderPreview()).toEqual({ recipients: 3, subject: 'Complete seus dados', html: '<p>Oi</p>' })
    expect(await attendees.remind(3)).toMatchObject({ ok: false, message: 'Confirme a cobrança por e-mail para 4 pessoas.' })
    expect(attendeesService.remind).toHaveBeenCalledWith('ed-1', 3)
  })
})
