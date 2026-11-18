import { beforeEach, describe, expect, it, vi } from 'vitest'
import speakersService from '../services/speakersService'
import { SpeakersError } from '../services/speakersError'
import { useSpeakers } from './useSpeakers'
import { useSpeaker } from './useSpeaker'

vi.mock('../services/speakersService', () => ({
  default: { invite: vi.fn(), tracks: vi.fn(), formOptions: vi.fn(), list: vi.fn(), show: vi.fn(), remind: vi.fn(), attendance: vi.fn(), promotions: vi.fn(), promote: vi.fn(), updateLogistics: vi.fn(), extendDeadline: vi.fn() },
}))

const row = (name: string, state: string, status: string, label: string, number: number) => ({
  user_id: `u-${number}`, name, email: `${name.split(' ')[0]!.toLowerCase()}@x.dev`, state, nordeste: state !== 'SP',
  proposal: { id: `p-${number}`, number, title: `Palestra ${number}`, track: 'Backend', origin: number === 2 ? 'invited' : 'cfp' }, status, status_label: label, deadline_at: null, reminded_at: null, attended_at: number === 1 ? '2027-10-08T12:00:00Z' : null,
})
const list = {
  summary: { confirmed: 1, awaiting: 1, waitlisted: 1, slots: 24, nordeste_percent: 100, nordeste_quota_percent: 50 },
  coverage: ['AL', 'BA', 'CE', 'MA', 'PB', 'PE', 'PI', 'RN', 'SE'].map((state) => ({ state, confirmed: state === 'PB' ? 1 : 0 })),
  data: [row('Ana Martins', 'PB', 'confirmed', 'Confirmada', 1), row('Joana Souza', 'BA', 'pending', 'Aguardando resposta', 2), row('Rafael Freitas', 'SP', 'waitlisted', 'Lista de espera', 3)],
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(speakersService.list).mockResolvedValue(list)
  vi.mocked(speakersService.promotions).mockResolvedValue({ data: [
    { track: { id: 't-1', name: 'Backend', slots: 2 }, occupied: 1, open_slots: 1, candidates: [{ proposal_id: 'p-3', number: 3, title: 'Palestra 3', final_score: 3.4 }] },
    { track: { id: 't-2', name: 'Carreira', slots: 3 }, occupied: 3, open_slots: 0, candidates: [] },
  ] })
})

describe('useSpeakers', () => {
  it('carrega indicadores, cobertura, vagas abertas e a lista', async () => {
    const speakers = useSpeakers('ed-1')
    await speakers.load()

    expect(speakers.summary.value).toMatchObject({ confirmed: 1, slots: 24, nordestePercent: 100 })
    expect(speakers.missingStates.value).toEqual(['AL', 'BA', 'CE', 'MA', 'PE', 'PI', 'RN', 'SE'])
    expect(speakers.promotions.value).toEqual([{ track: 'Backend', openSlots: 1, next: { proposalId: 'p-3', number: 3, title: 'Palestra 3', finalScore: 3.4 } }])
    expect(speakers.rows.value[0]).toMatchObject({ initials: 'AM', attended: true, status: 'confirmed' })
  })

  it('filtra por status e busca por nome, e-mail ou palestra', async () => {
    const speakers = useSpeakers('ed-1')
    await speakers.load()

    speakers.filter.value = 'waitlisted'
    expect(speakers.filtered.value.map((item) => item.name)).toEqual(['Rafael Freitas'])
    speakers.filter.value = 'all'
    speakers.search.value = 'joana@'
    expect(speakers.filtered.value.map((item) => item.name)).toEqual(['Joana Souza'])
    expect(speakers.counts.value).toEqual({ all: 3, confirmed: 1, pending: 1, waitlisted: 1 })
  })

  it('promove da lista de espera e recarrega, ou devolve a recusa do backend', async () => {
    vi.mocked(speakersService.promote).mockResolvedValueOnce({ data: { proposal_id: 'p-3' } }).mockRejectedValueOnce(new SpeakersError('x', 422, { proposal_id: ['Esta proposta não é a próxima da lista de espera para uma vaga aberta.'] }))
    const speakers = useSpeakers('ed-1')
    await speakers.load()

    expect(await speakers.promote('p-3')).toBeNull()
    expect(speakersService.list).toHaveBeenCalledTimes(2)
    expect(await speakers.promote('p-3')).toBe('Esta proposta não é a próxima da lista de espera para uma vaga aberta.')
  })
})

describe('convite direto', () => {
  it('marca a palestra convidada, monta as opções e convida', async () => {
    vi.mocked(speakersService.tracks).mockResolvedValue({ data: [{ id: 't-1', name: 'Backend' }] })
    vi.mocked(speakersService.formOptions).mockResolvedValue({ meta: { form: { formats: [{ id: 'talk', label: 'Palestra de 30 min' }] } } })
    vi.mocked(speakersService.invite).mockResolvedValueOnce({ data: { proposal_id: 'p-9', number: 9, user_id: 'u-9', deadline_at: '2027-05-11T23:59:59Z' } })
    const speakers = useSpeakers('ed-1')
    await speakers.load()

    expect(speakers.rows.value.map((row) => row.proposal.invited)).toEqual([false, true, false])
    expect(await speakers.inviteOptions()).toEqual({ tracks: [{ value: 't-1', label: 'Backend' }], formats: [{ value: 'talk', label: 'Palestra de 30 min' }] })
    expect(await speakers.invite({ name: ' Keynote ', email: 'k@x.dev', title: 'O futuro', trackId: 't-1', format: 'talk', summary: '' })).toEqual({ ok: true, number: 9 })
    expect(speakersService.invite).toHaveBeenCalledWith('ed-1', { name: 'Keynote', email: 'k@x.dev', title: 'O futuro', track_id: 't-1', format: 'talk', summary: null })
    expect(speakersService.list).toHaveBeenCalledTimes(2)
  })

  it('traduz a recusa de quem já tem palestra aprovada', async () => {
    vi.mocked(speakersService.invite).mockRejectedValueOnce(new SpeakersError('x', 422, { email: ['Essa pessoa já tem palestra aprovada nesta edição.'], track_id: ['Trilha inválida.'] }))
    const speakers = useSpeakers('ed-1')

    expect(await speakers.invite({ name: 'K', email: 'k@x.dev', title: 'T', trackId: 'x', format: 'talk', summary: '' })).toEqual({
      ok: false, message: 'Essa pessoa já tem palestra aprovada nesta edição.', fieldErrors: { email: 'Essa pessoa já tem palestra aprovada nesta edição.', trackId: 'Trilha inválida.' },
    })
  })
})

describe('useSpeaker', () => {
  const detail = (canViewPrivate: boolean) => ({ data: {
    user_id: 'u-1', name: 'Ana Martins', email: 'ana@x.dev', can_view_private_data: canViewPrivate,
    profile: { display_name: 'Ana Martins', job_title: 'Engenheira', company: 'Acme', bio: 'Bio', city: 'João Pessoa', state: 'PB', links: { website: null, github: 'https://github.com/ana', linkedin: null }, photo_url: null },
    proposals: [{ id: 'p-1', number: 1, title: 'Octane', track: 'Backend', origin: 'cfp', origin_label: 'Pelo CFP', format_label: 'Palestra de 30 min', duration_minutes: 30, decision: 'accepted', decision_label: 'Aprovada', status: 'confirmed', status_label: 'Confirmada', deadline_at: null, responded_at: null, reminded_at: null, attended_at: null, reason: null }],
    logistics: { user_id: 'u-1', origin_city: 'Recife', origin_state: 'PE', needs_travel: true, needs_lodging: true, travel_paid_by_organization: false, lodging_paid_by_organization: true, organization_notes: null, restrictions: canViewPrivate ? { accessibility_needs: 'Rampa', dietary_restrictions: null } : null },
  } })

  it('traduz perfil, palestras e logística, e não traz acessibilidade sem permissão', async () => {
    vi.mocked(speakersService.show).mockResolvedValueOnce(detail(false))
    const speaker = useSpeaker('ed-1', 'u-1')
    await speaker.load()

    expect(speaker.speaker.value).toMatchObject({ initials: 'AM', nordeste: true, links: [{ label: 'GitHub', url: 'https://github.com/ana' }], canViewPrivateData: false })
    expect(speaker.speaker.value?.logistics).toMatchObject({ lodgingPaidByOrganization: true, organizationNotes: '', accessibilityNeeds: null })
  })

  it('mostra "não encontrado" quando a pessoa não é palestrante da edição', async () => {
    vi.mocked(speakersService.show).mockRejectedValueOnce(new SpeakersError('x', 404))
    const speaker = useSpeaker('ed-1', 'u-9')
    await speaker.load()

    expect(speaker.state.value).toBe('not-found')
  })

  it('salva a logística com notas vazias como nulas e recarrega', async () => {
    vi.mocked(speakersService.show).mockResolvedValue(detail(true))
    vi.mocked(speakersService.updateLogistics).mockResolvedValueOnce({ data: detail(true).data.logistics })
    const speaker = useSpeaker('ed-1', 'u-1')
    await speaker.load()

    expect(await speaker.saveLogistics({ travelPaidByOrganization: true, lodgingPaidByOrganization: true, organizationNotes: '  ' })).toMatchObject({ ok: true })
    expect(speakersService.updateLogistics).toHaveBeenCalledWith('ed-1', 'u-1', { travel_paid_by_organization: true, lodging_paid_by_organization: true, organization_notes: null })
    expect(speaker.speaker.value?.logistics?.accessibilityNeeds).toBe('Rampa')
  })
})
