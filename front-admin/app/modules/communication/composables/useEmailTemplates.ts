import { computed, ref } from 'vue'
import communicationService, { type TemplatePayload } from '../services/communicationService'
import { CommunicationError } from '../services/communicationError'
import locale from '../locales/pt-br'
import { unknownVariables, wrap } from './format'
import type { Content, ContentField, EmailTemplate, Failure, MutationResult, Preview, TestResult } from '../types'

export const translateTemplate = (payload: TemplatePayload): EmailTemplate => ({
  id: payload.id,
  label: payload.label,
  trigger: payload.trigger,
  transactional: payload.transactional,
  variables: payload.variables,
  actionLabel: payload.action_label,
  subject: payload.subject,
  body: payload.body,
  customized: payload.customized,
  lastSentAt: payload.last_sent_at,
})

export const unknownMessage = (unknown: string[], known: string[]) =>
  locale.unknownVariables.replace('{invalidas}', unknown.map(wrap).join(', ')).replace('{validas}', known.map(wrap).join(', '))

function failure(error: unknown): Failure {
  if (error instanceof CommunicationError && error.statusCode === 422 && error.fieldErrors) {
    const fieldErrors: Partial<Record<ContentField, string>> = {}
    for (const field of ['subject', 'body'] as const) {
      const message = error.fieldErrors[field]?.[0]
      if (message) fieldErrors[field] = message
    }
    return { ok: false, message: Object.values(fieldErrors)[0] ?? Object.values(error.fieldErrors)[0]?.[0] ?? locale.genericError, fieldErrors }
  }
  if (error instanceof CommunicationError && error.statusCode === 429) return { ok: false, message: locale.tooManyTests, fieldErrors: {} }
  if (error instanceof CommunicationError && error.statusCode === 403) return { ok: false, message: locale.forbidden, fieldErrors: {} }
  return { ok: false, message: locale.genericError, fieldErrors: {} }
}

const PREVIEW_DELAY = 400

export function useEmailTemplates(editionId: string) {
  const templates = ref<EmailTemplate[]>([])
  const state = ref<'loading' | 'ready' | 'error'>('loading')
  const selectedId = ref<string | null>(null)
  const draft = ref<Content>({ subject: '', body: '' })
  const serverErrors = ref<Partial<Record<ContentField, string>>>({})
  const preview = ref<Preview | null>(null)
  const previewState = ref<'idle' | 'loading' | 'ready' | 'blocked' | 'error'>('idle')
  let previewTimer: ReturnType<typeof setTimeout> | null = null
  let previewRequest = 0

  const selected = computed(() => templates.value.find((template) => template.id === selectedId.value) ?? null)
  const dirty = computed(() => !!selected.value && (draft.value.subject !== selected.value.subject || draft.value.body !== selected.value.body))

  const errors = computed<Record<ContentField, string | null>>(() => {
    const known = selected.value?.variables ?? []
    const check = (field: ContentField) => {
      const text = draft.value[field]
      if (text.trim() === '') return locale.required
      const unknown = unknownVariables(text, known)
      if (unknown.length) return unknownMessage(unknown, known)
      return serverErrors.value[field] ?? null
    }
    return { subject: check('subject'), body: check('body') }
  })
  const valid = computed(() => !errors.value.subject && !errors.value.body)

  const content = (): Content => ({ subject: draft.value.subject, body: draft.value.body })

  async function refreshPreview() {
    if (previewTimer) clearTimeout(previewTimer)
    previewTimer = null
    const template = selected.value
    if (!template) return
    if (!valid.value) {
      previewState.value = 'blocked'
      return
    }
    const request = ++previewRequest
    previewState.value = 'loading'
    try {
      const response = await communicationService.preview(editionId, template.id, content())
      if (request !== previewRequest) return
      preview.value = { subject: response.data.subject, html: response.data.html }
      previewState.value = 'ready'
    } catch (error) {
      if (request !== previewRequest) return
      const result = failure(error)
      serverErrors.value = result.fieldErrors
      previewState.value = Object.keys(result.fieldErrors).length ? 'blocked' : 'error'
    }
  }

  function edit(field: ContentField, value: string) {
    draft.value = { ...draft.value, [field]: value }
    if (serverErrors.value[field]) serverErrors.value = { ...serverErrors.value, [field]: undefined }
    if (previewTimer) clearTimeout(previewTimer)
    previewTimer = setTimeout(refreshPreview, PREVIEW_DELAY)
  }

  function select(templateId: string) {
    const template = templates.value.find((entry) => entry.id === templateId)
    if (!template) return
    selectedId.value = template.id
    draft.value = { subject: template.subject, body: template.body }
    serverErrors.value = {}
    preview.value = null
    void refreshPreview()
  }

  async function load() {
    state.value = 'loading'
    try {
      templates.value = (await communicationService.list(editionId)).data.map(translateTemplate)
      state.value = 'ready'
    } catch {
      state.value = 'error'
      return
    }
    const keep = templates.value.find((template) => template.id === selectedId.value) ?? templates.value[0]
    if (keep) select(keep.id)
  }

  function replace(payload: TemplatePayload) {
    const template = translateTemplate(payload)
    templates.value = templates.value.map((entry) => (entry.id === template.id ? template : entry))
    draft.value = { subject: template.subject, body: template.body }
    serverErrors.value = {}
  }

  async function save(): Promise<MutationResult> {
    const template = selected.value
    if (!template) return { ok: false, message: locale.genericError, fieldErrors: {} }
    if (!valid.value) return { ok: false, message: errors.value.subject ?? errors.value.body ?? locale.genericError, fieldErrors: {} }
    try {
      replace((await communicationService.save(editionId, template.id, content())).data)
      return { ok: true }
    } catch (error) {
      const result = failure(error)
      serverErrors.value = result.fieldErrors
      return result
    }
  }

  async function restore(): Promise<MutationResult> {
    const template = selected.value
    if (!template) return { ok: false, message: locale.genericError, fieldErrors: {} }
    try {
      replace((await communicationService.restore(editionId, template.id)).data)
      void refreshPreview()
      return { ok: true }
    } catch (error) {
      return failure(error)
    }
  }

  async function sendTest(): Promise<TestResult> {
    const template = selected.value
    if (!template) return { ok: false, message: locale.genericError, fieldErrors: {} }
    if (!valid.value) return { ok: false, message: errors.value.subject ?? errors.value.body ?? locale.genericError, fieldErrors: {} }
    try {
      const delivery = (await communicationService.sendTest(editionId, template.id, content())).data
      return { ok: true, email: delivery.to_email }
    } catch (error) {
      const result = failure(error)
      serverErrors.value = result.fieldErrors
      return result
    }
  }

  function dispose() {
    if (previewTimer) clearTimeout(previewTimer)
    previewRequest++
  }

  return { templates, state, selectedId, selected, draft, dirty, errors, valid, preview, previewState, load, select, edit, refreshPreview, save, restore, sendTest, dispose }
}
