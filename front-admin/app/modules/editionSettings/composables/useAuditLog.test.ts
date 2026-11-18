import { beforeEach, describe, expect, it, vi } from 'vitest'
import editionSettingsService from '../services/editionSettingsService'
import { describe as describeChange, useAuditLog } from './useAuditLog'

vi.mock('../services/editionSettingsService', () => ({ default: { audit: vi.fn() } }))

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(editionSettingsService.audit).mockResolvedValue({
    data: [
      { id: 'a-1', action: 'edition.status_changed', subject_type: 'edition', subject_id: 'ed-1', actor: { id: 'u-1', name: 'Mariana Sales' }, before: { status: 'cfp_open' }, after: { status: 'sales_open' }, created_at: '2027-03-10T12:00:00Z' },
      { id: 'a-2', action: 'branding.theme_published', subject_type: 'edition', subject_id: 'ed-1', actor: null, before: null, after: null, created_at: '2027-03-09T12:00:00Z' },
    ],
    meta: { current_page: 1, last_page: 3, total: 120 },
  })
})

describe('useAuditLog', () => {
  it('traduz a ação, quem fez e o que mudou', async () => {
    const audit = useAuditLog('ed-1')
    await audit.load()

    expect(audit.entries.value[0]).toMatchObject({ actionLabel: 'Mudou a etapa da edição', actor: { name: 'Mariana Sales' }, detail: 'etapa: CFP aberto → Vendas abertas' })
    expect(audit.entries.value[1]).toMatchObject({ actionLabel: 'Publicou a identidade da edição', actor: null, detail: '' })
    expect(audit.people.value).toEqual([{ id: 'u-1', name: 'Mariana Sales' }])
    expect(audit.lastPage.value).toBe(3)
  })

  it('filtra por ação, pessoa e período no horário de Brasília', async () => {
    const audit = useAuditLog('ed-1')
    Object.assign(audit.filters, { action: 'team.invited', actorId: 'u-1', from: '2027-03-01', to: '2027-03-10' })
    await audit.load(2)

    expect(editionSettingsService.audit).toHaveBeenCalledWith('ed-1', { page: 2, action: 'team.invited', actor_id: 'u-1', from: '2027-03-01T03:00:00.000Z', to: '2027-03-11T02:59:59.000Z' })
    await audit.clear()
    expect(editionSettingsService.audit).toHaveBeenLastCalledWith('ed-1', { page: 1, action: undefined, actor_id: undefined, from: undefined, to: undefined })
  })

  it('descreve mudanças novas e alteradas', () => {
    expect(describeChange({ site_mode: 'platform', external_site_url: null }, { site_mode: 'external', external_site_url: 'https://x.dev' })).toBe('modo do site: site da plataforma → site próprio · site da comunidade: — → https://x.dev')
    expect(describeChange(null, { role: 'avaliador', email: 'a@x.dev' })).toBe('papel: Revisor do CFP · e-mail: a@x.dev')
  })
})
