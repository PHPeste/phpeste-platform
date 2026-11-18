import { computed, reactive, ref } from 'vue'
import themeService, { type PresetsPayload, type ThemeSettingsPayload } from '../services/themeService'
import { ThemeError } from '../services/themeError'
import locale from '../locales/pt-br'
import { evaluateContrast, HEX } from './contrast'
import type { EditorMeta, LogoSlot, Preset, ThemeState } from '../types'

function translateState(payload: ThemeSettingsPayload['data']): ThemeState {
  return {
    draft: payload.draft,
    published: payload.published,
    publishedAt: payload.published_at,
    hasUnpublishedChanges: payload.has_unpublished_changes,
    canRevert: payload.can_revert,
  }
}

function translatePresets(payload: PresetsPayload): { presets: Preset[], meta: EditorMeta } {
  return {
    presets: payload.data.map((preset) => ({ id: preset.id, label: preset.label, description: preset.description, colors: preset.theme.colors })),
    meta: { tokens: payload.meta.tokens, pairs: payload.meta.contrast_pairs, fonts: payload.meta.fonts, radius: payload.meta.radius },
  }
}

export function useThemeEditor(editionId: string) {
  const state = ref<ThemeState | null>(null)
  const presets = ref<Preset[]>([])
  const meta = ref<EditorMeta>({ tokens: [], pairs: [], fonts: [], radius: { min: 0, max: 24 } })
  const loadState = ref<'loading' | 'ready' | 'error'>('loading')
  const busy = ref<string | null>(null)
  const errorMessage = ref<string | null>(null)
  const uploadingSlot = ref<LogoSlot | null>(null)

  const form = reactive({ colors: {} as Record<string, string>, heading: '', body: '', radius: 10 })

  function resetForm() {
    if (!state.value) return
    form.colors = { ...state.value.draft.colors }
    form.heading = state.value.draft.fonts.heading
    form.body = state.value.draft.fonts.body
    form.radius = state.value.draft.radius
  }

  function accept(payload: ThemeSettingsPayload, keepForm = false) {
    state.value = translateState(payload.data)
    if (!keepForm) resetForm()
  }

  async function load(): Promise<void> {
    loadState.value = 'loading'

    try {
      const [theme, presetsPayload] = await Promise.all([themeService.get(editionId), themeService.presets()])
      const translated = translatePresets(presetsPayload)
      presets.value = translated.presets
      meta.value = translated.meta
      accept(theme)
      loadState.value = 'ready'
    } catch {
      loadState.value = 'error'
    }
  }

  const contrast = computed(() => evaluateContrast(form.colors, meta.value.pairs, meta.value.tokens))
  const failing = computed(() => contrast.value.filter((pair) => !pair.passes))
  const invalidTokens = computed(() => Object.entries(form.colors).filter(([, value]) => !HEX.test(value)).map(([token]) => token))

  const isDirty = computed(() => {
    const draft = state.value?.draft
    if (!draft) return false
    return form.heading !== draft.fonts.heading
      || form.body !== draft.fonts.body
      || form.radius !== draft.radius
      || Object.keys(draft.colors).some((token) => form.colors[token]?.toUpperCase() !== draft.colors[token]?.toUpperCase())
  })

  const canPublish = computed(() => failing.value.length === 0 && invalidTokens.value.length === 0 && (isDirty.value || !!state.value?.hasUnpublishedChanges))

  async function run(action: string, request: () => Promise<ThemeSettingsPayload>, keepForm = false): Promise<boolean> {
    busy.value = action
    errorMessage.value = null

    try {
      accept(await request(), keepForm)
      return true
    } catch (error) {
      errorMessage.value = error instanceof ThemeError && error.statusCode === 422 && error.fieldErrors
        ? Object.values(error.fieldErrors).flat().join(' ')
        : locale.genericError
      return false
    } finally {
      busy.value = null
    }
  }

  const draftBody = () => ({ colors: { ...form.colors }, fonts: { heading: form.heading, body: form.body }, radius: form.radius })

  async function saveDraft(): Promise<boolean> {
    return run('save', () => themeService.saveDraft(editionId, draftBody()))
  }

  async function publish(): Promise<boolean> {
    if (!canPublish.value) return false
    if (isDirty.value && !(await saveDraft())) return false
    return run('publish', () => themeService.publish(editionId))
  }

  const applyPreset = (preset: string) => run('preset', () => themeService.applyPreset(editionId, preset))
  const discard = () => run('discard', () => themeService.discard(editionId))
  const revert = () => run('revert', () => themeService.revert(editionId))

  async function uploadLogo(slot: LogoSlot, file: File): Promise<boolean> {
    uploadingSlot.value = slot
    try {
      return await run('logo', () => themeService.uploadLogo(editionId, slot, file), true)
    } finally {
      uploadingSlot.value = null
    }
  }

  const removeLogo = (slot: LogoSlot) => run('logo', () => themeService.removeLogo(editionId, slot), true)

  return {
    state, presets, meta, loadState, busy, errorMessage, uploadingSlot, form,
    contrast, failing, invalidTokens, isDirty, canPublish,
    load, saveDraft, publish, applyPreset, discard, revert, uploadLogo, removeLogo,
  }
}
