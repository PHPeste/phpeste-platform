import { beforeEach, describe, expect, it, vi } from 'vitest'
import editionSettingsService, { type EditionPayload } from '../services/editionSettingsService'
import { EditionSettingsError } from '../services/editionSettingsError'
import { useEditionSettings } from './useEditionSettings'

vi.mock('../services/editionSettingsService', () => ({
  default: { show: vi.fn(), update: vi.fn(), changeStatus: vi.fn(), domains: vi.fn(), addDomain: vi.fn(), verifyDomain: vi.fn(), makePrimary: vi.fn(), removeDomain: vi.fn(), audit: vi.fn() },
}))

const payload = (overrides: Partial<EditionPayload> = {}): EditionPayload => ({
  id: 'ed-1', name: 'PHPeste 2027', slug: '2027', status: 'cfp_open', status_label: 'CFP aberto',
  status_options: [{ status: 'sales_open', label: 'Vendas abertas', unlocks: ['Venda de ingressos'], blocks: ['Não dá para voltar'] }],
  starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB', timezone: 'America/Fortaleza',
  site_mode: 'platform', external_site_url: null, site_url: null, privacy_contact_email: null, privacy_policy_url: null, anonymize_after_event: false,
  ...overrides,
})

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(editionSettingsService.show).mockResolvedValue({ data: payload() })
  vi.mocked(editionSettingsService.domains).mockResolvedValue({ data: [] })
})

describe('useEditionSettings', () => {
  it('salva num PATCH só o que mudou, de qualquer aba, e avisa o painel', async () => {
    const changed = vi.fn()
    vi.mocked(editionSettingsService.update).mockResolvedValueOnce({ data: payload({ name: 'PHPeste 2027 · JP', site_mode: 'external', external_site_url: 'https://phpeste.php-pb.net' }) })
    const settings = useEditionSettings('ed-1', changed)
    await settings.load()

    settings.general.name = 'PHPeste 2027 · JP'
    settings.site.siteMode = 'external'
    settings.site.externalSiteUrl = ' https://phpeste.php-pb.net '
    expect(settings.dirty.value).toEqual({ general: true, site: true, privacy: false })

    expect(await settings.save()).toEqual({ ok: true })
    expect(editionSettingsService.update).toHaveBeenCalledWith('ed-1', {
      name: 'PHPeste 2027 · JP', starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB', timezone: 'America/Fortaleza',
      site_mode: 'external', external_site_url: 'https://phpeste.php-pb.net',
    })
    expect(changed).toHaveBeenCalled()
    expect(settings.anyDirty.value).toBe(false)
  })

  it('traduz o erro do campo para o nome usado na tela', async () => {
    vi.mocked(editionSettingsService.update).mockRejectedValueOnce(new EditionSettingsError('x', 422, { external_site_url: ['Informe o endereço do site da comunidade para usar o site próprio.'] }))
    const settings = useEditionSettings('ed-1')
    await settings.load()
    settings.site.siteMode = 'external'

    expect(await settings.save()).toEqual({ ok: false, message: 'Informe o endereço do site da comunidade para usar o site próprio.', fieldErrors: { externalSiteUrl: 'Informe o endereço do site da comunidade para usar o site próprio.' } })
  })

  it('muda a etapa e recarrega a edição ativa', async () => {
    const changed = vi.fn()
    vi.mocked(editionSettingsService.changeStatus).mockResolvedValueOnce({ data: payload({ status: 'sales_open', status_label: 'Vendas abertas', status_options: [] }) })
    const settings = useEditionSettings('ed-1', changed)
    await settings.load()

    expect(await settings.changeStatus('sales_open')).toEqual({ ok: true })
    expect(settings.edition.value?.statusLabel).toBe('Vendas abertas')
    expect(changed).toHaveBeenCalled()
  })

  it('adiciona domínio em minúsculas e diz se a verificação passou', async () => {
    vi.mocked(editionSettingsService.addDomain).mockResolvedValueOnce({ data: {} as never })
    vi.mocked(editionSettingsService.verifyDomain).mockResolvedValueOnce({ data: { verified: false } as never })
    const settings = useEditionSettings('ed-1')
    await settings.load()

    await settings.addDomain(' CFP.phpeste.php-pb.net ')
    expect(editionSettingsService.addDomain).toHaveBeenCalledWith('ed-1', 'cfp.phpeste.php-pb.net')
    expect(await settings.verifyDomain({ id: 'd-1' } as never)).toBe(false)
  })
})
