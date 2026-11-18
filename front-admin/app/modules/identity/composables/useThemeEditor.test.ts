import { beforeEach, describe, expect, it, vi } from 'vitest'
import themeService from '../services/themeService'
import { ThemeError } from '../services/themeError'
import { useThemeEditor } from './useThemeEditor'

vi.mock('../services/themeService', () => ({
  default: { get: vi.fn(), presets: vi.fn(), saveDraft: vi.fn(), applyPreset: vi.fn(), discard: vi.fn(), publish: vi.fn(), revert: vi.fn(), uploadLogo: vi.fn(), removeLogo: vi.fn() },
}))

const theme = { colors: { ink: '#3A1607', bg: '#FBF4E4' }, fonts: { heading: 'Lexend', body: 'Lexend' }, radius: 10, logos: { primary: null, dark: null, favicon: null, app_icon: null } }
const settings = (overrides: Record<string, unknown> = {}) => ({ data: { edition_id: 'ed-1', draft: theme, published: null, published_at: null, has_unpublished_changes: true, can_revert: false, ...overrides } })
const presets = {
  data: [{ id: 'sertao', label: 'Sertão', description: 'Padrão', theme: { colors: theme.colors } }],
  meta: { tokens: [{ id: 'ink', label: 'Texto' }, { id: 'bg', label: 'Fundo' }], contrast_pairs: [{ foreground: 'ink', background: 'bg', minimum: 4.5 }], fonts: ['Lexend', 'Inter'], radius: { min: 0, max: 24 } },
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(themeService.get).mockResolvedValue(settings())
  vi.mocked(themeService.presets).mockResolvedValue(presets)
})

describe('useThemeEditor', () => {
  it('carrega o rascunho, os presets e as regras de contraste', async () => {
    const editor = useThemeEditor('ed-1')
    await editor.load()

    expect(editor.loadState.value).toBe('ready')
    expect(editor.form.colors.ink).toBe('#3A1607')
    expect(editor.contrast.value[0]).toMatchObject({ label: 'Texto sobre Fundo', passes: true })
    expect(editor.isDirty.value).toBe(false)
  })

  it('recalcula o contraste a cada mudança e bloqueia a publicação quando um par falha', async () => {
    const editor = useThemeEditor('ed-1')
    await editor.load()

    editor.form.colors = { ...editor.form.colors, ink: '#EEE6D6' }

    expect(editor.isDirty.value).toBe(true)
    expect(editor.failing.value).toHaveLength(1)
    expect(editor.canPublish.value).toBe(false)
    expect(await editor.publish()).toBe(false)
    expect(themeService.publish).not.toHaveBeenCalled()
  })

  it('publicar salva o rascunho editado e depois publica', async () => {
    vi.mocked(themeService.saveDraft).mockResolvedValueOnce(settings({ draft: { ...theme, colors: { ...theme.colors, ink: '#2B0F0B' } } }))
    vi.mocked(themeService.publish).mockResolvedValueOnce(settings({ has_unpublished_changes: false, published_at: '2027-03-10T12:00:00Z' }))

    const editor = useThemeEditor('ed-1')
    await editor.load()
    editor.form.colors = { ...editor.form.colors, ink: '#2B0F0B' }

    expect(await editor.publish()).toBe(true)
    expect(themeService.saveDraft).toHaveBeenCalledWith('ed-1', { colors: { ink: '#2B0F0B', bg: '#FBF4E4' }, fonts: { heading: 'Lexend', body: 'Lexend' }, radius: 10 })
    expect(themeService.publish).toHaveBeenCalledWith('ed-1')
    expect(editor.state.value?.hasUnpublishedChanges).toBe(false)
  })

  it('mostra a explicação do backend quando ele recusa o contraste', async () => {
    vi.mocked(themeService.saveDraft).mockRejectedValueOnce(new ThemeError('x', 422, { colors: ['Contraste insuficiente entre Texto (ink) e Fundo (bg): 1,20:1, o mínimo é 4,5:1.'] }))

    const editor = useThemeEditor('ed-1')
    await editor.load()
    editor.form.radius = 12

    expect(await editor.saveDraft()).toBe(false)
    expect(editor.errorMessage.value).toBe('Contraste insuficiente entre Texto (ink) e Fundo (bg): 1,20:1, o mínimo é 4,5:1.')
  })

  it('enviar logo não apaga as cores que ainda estão sendo editadas', async () => {
    vi.mocked(themeService.uploadLogo).mockResolvedValueOnce(settings({ draft: { ...theme, logos: { ...theme.logos, primary: 'https://cdn/logo.png' } } }))

    const editor = useThemeEditor('ed-1')
    await editor.load()
    editor.form.colors = { ...editor.form.colors, ink: '#2B0F0B' }
    await editor.uploadLogo('primary', new File(['x'], 'logo.png', { type: 'image/png' }))

    expect(editor.state.value?.draft.logos.primary).toBe('https://cdn/logo.png')
    expect(editor.form.colors.ink).toBe('#2B0F0B')
  })
})
