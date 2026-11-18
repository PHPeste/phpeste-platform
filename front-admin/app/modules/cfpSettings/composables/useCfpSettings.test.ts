import { beforeEach, describe, expect, it, vi } from 'vitest'
import cfpSettingsService from '../services/cfpSettingsService'
import { CfpSettingsError } from '../services/cfpSettingsError'
import { useCfpSettings } from './useCfpSettings'

vi.mock('../services/cfpSettingsService', () => ({
  default: { settings: vi.fn(), updateSettings: vi.fn(), tracks: vi.fn(), createTrack: vi.fn(), updateTrack: vi.fn(), deleteTrack: vi.fn(), criteria: vi.fn(), createCriterion: vi.fn(), updateCriterion: vi.fn(), deleteCriterion: vi.fn() },
}))

const settingsPayload = (overrides: Record<string, unknown> = {}) => ({
  data: {
    status: 'open', status_label: 'CFP aberto', days_until_close: 36,
    opens_at: '2027-02-01T03:00:00Z', closes_at: '2027-04-16T02:59:59Z', results_at: '2027-05-01T02:59:59Z',
    anonymous_review: true, require_nordeste_quota: true, nordeste_quota_percent: 50, proposals_per_person: 2, ...overrides,
  },
  meta: { form: { formats: [{ id: 'talk', label: 'Palestra de 30 min', minutes: 30 }], levels: [{ id: 'beginner', label: 'Iniciante' }], languages: [{ id: 'pt_BR', label: 'Português' }], limits: { title: 90, summary: 3000, audience: 1000 } } },
})
const tracks = { data: [{ id: 't-1', name: 'Backend', slots: 6, position: 1, proposals_count: 14 }, { id: 't-2', name: 'Carreira', slots: 5, position: 2, proposals_count: 9 }] }
const criteria = (reviews = 0) => ({ data: [{ id: 'c-1', name: 'Relevância', description: null, weight: 3, position: 1 }, { id: 'c-2', name: 'Clareza', description: 'Título claro?', weight: 1, position: 2 }], meta: { submitted_reviews: reviews } })

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(cfpSettingsService.settings).mockResolvedValue(settingsPayload())
  vi.mocked(cfpSettingsService.tracks).mockResolvedValue(tracks)
  vi.mocked(cfpSettingsService.criteria).mockResolvedValue(criteria())
})

describe('useCfpSettings', () => {
  it('carrega janela em dias de Brasília, regras, trilhas com propostas e critérios', async () => {
    const cfp = useCfpSettings('ed-1')
    await cfp.load()

    expect(cfp.state.value).toBe('ready')
    expect(cfp.general).toMatchObject({ opensOn: '2027-02-01', closesOn: '2027-04-15', resultsOn: '2027-04-30', proposalsPerPerson: 2 })
    expect(cfp.tracks.value[0]).toEqual({ id: 't-1', name: 'Backend', slots: 6, position: 1, proposals: 14 })
    expect(cfp.criteria.value[0]!.description).toBe('')
    expect(cfp.totalWeight.value).toBe(4)
    expect(cfp.generalDirty.value).toBe(false)
  })

  it('valida a janela antes de enviar e salva o dia inteiro', async () => {
    vi.mocked(cfpSettingsService.updateSettings).mockResolvedValueOnce(settingsPayload({ proposals_per_person: 3 }))
    const cfp = useCfpSettings('ed-1')
    await cfp.load()

    cfp.general.closesOn = '2027-01-15'
    expect(await cfp.saveGeneral()).toMatchObject({ ok: false })
    expect(cfp.generalErrors.value.closesOn).toBe('O fechamento precisa ser depois da abertura.')
    expect(cfpSettingsService.updateSettings).not.toHaveBeenCalled()

    cfp.general.closesOn = '2027-04-15'
    cfp.general.proposalsPerPerson = 3
    expect(await cfp.saveGeneral()).toEqual({ ok: true })
    expect(cfpSettingsService.updateSettings).toHaveBeenCalledWith('ed-1', expect.objectContaining({ opens_at: '2027-02-01T03:00:00.000Z', closes_at: '2027-04-16T02:59:59.000Z', proposals_per_person: 3 }))
    expect(cfp.generalDirty.value).toBe(false)
  })

  it('traduz o erro de campo do backend para o campo da tela', async () => {
    vi.mocked(cfpSettingsService.updateSettings).mockRejectedValueOnce(new CfpSettingsError('x', 422, { nordeste_quota_percent: ['Use um valor de 0 a 100.'] }))
    const cfp = useCfpSettings('ed-1')
    await cfp.load()
    cfp.general.nordesteQuotaPercent = 150

    await cfp.saveGeneral()
    expect(cfp.generalErrors.value).toEqual({ nordesteQuotaPercent: 'Use um valor de 0 a 100.' })
  })

  it('pede confirmação de impacto para mudar peso ou remover critério quando há avaliações, mas não para renomear', async () => {
    vi.mocked(cfpSettingsService.criteria).mockResolvedValue(criteria(12))
    vi.mocked(cfpSettingsService.updateCriterion).mockResolvedValue({ data: criteria().data[0]! })
    const cfp = useCfpSettings('ed-1')
    await cfp.load()
    const relevance = cfp.criteria.value[0]!

    expect(await cfp.saveCriterion(relevance, { name: 'Relevância', description: '', weight: 5 })).toEqual({ ok: false, needsConfirmation: true })
    expect(await cfp.removeCriterion(relevance)).toEqual({ ok: false, needsConfirmation: true })
    expect(cfpSettingsService.updateCriterion).not.toHaveBeenCalled()

    expect(await cfp.saveCriterion(relevance, { name: 'Relevância para a comunidade', description: '', weight: 3 })).toEqual({ ok: true })
    expect(await cfp.saveCriterion(relevance, { name: 'Relevância', description: '', weight: 5 }, true)).toEqual({ ok: true })
    expect(cfpSettingsService.updateCriterion).toHaveBeenLastCalledWith('ed-1', 'c-1', { name: 'Relevância', description: null, weight: 5, confirm: true })
  })

  it('volta a pedir confirmação se o backend disser que surgiram avaliações', async () => {
    vi.mocked(cfpSettingsService.createCriterion).mockRejectedValueOnce(new CfpSettingsError('x', 422, { confirm: ['Já existem 1 avaliação.'] }))
    const cfp = useCfpSettings('ed-1')
    await cfp.load()

    expect(await cfp.saveCriterion(null, { name: 'Originalidade', description: '', weight: 2 })).toEqual({ ok: false, needsConfirmation: true })
  })

  it('reordena trilhas trocando as posições', async () => {
    vi.mocked(cfpSettingsService.updateTrack).mockResolvedValue({ data: tracks.data[0]! })
    const cfp = useCfpSettings('ed-1')
    await cfp.load()

    await cfp.moveTrack(cfp.tracks.value[1]!, -1)
    expect(cfpSettingsService.updateTrack).toHaveBeenCalledWith('ed-1', 't-2', { position: 1 })
    expect(cfpSettingsService.updateTrack).toHaveBeenCalledWith('ed-1', 't-1', { position: 2 })
  })
})
