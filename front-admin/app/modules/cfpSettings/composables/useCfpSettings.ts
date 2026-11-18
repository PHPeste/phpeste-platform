import { computed, reactive, ref } from 'vue'
import cfpSettingsService, { type CriterionPayload, type SettingsPayload, type TrackPayload } from '../services/cfpSettingsService'
import { CfpSettingsError } from '../services/cfpSettingsError'
import locale from '../locales/pt-br'
import { endOfDay, startOfDay, toDay } from './dates'
import type { CfpGeneral, CfpSettings, Criterion, CriterionInput, MutationResult, ProposalFormOptions, Track, TrackInput } from '../types'

function translateSettings(payload: SettingsPayload['data']): CfpSettings {
  return {
    status: payload.status,
    statusLabel: payload.status_label,
    daysUntilClose: payload.days_until_close,
    opensOn: toDay(payload.opens_at),
    closesOn: toDay(payload.closes_at),
    resultsOn: toDay(payload.results_at),
    anonymousReview: payload.anonymous_review,
    requireNordesteQuota: payload.require_nordeste_quota,
    nordesteQuotaPercent: payload.nordeste_quota_percent,
    proposalsPerPerson: payload.proposals_per_person,
  }
}

function translateForm(payload: SettingsPayload['meta']['form']): ProposalFormOptions {
  return {
    formats: payload.formats.map(({ id, label }) => ({ id, label })),
    levels: payload.levels,
    languages: payload.languages,
    limits: payload.limits,
  }
}

const translateTrack = (payload: TrackPayload): Track => ({ id: payload.id, name: payload.name, slots: payload.slots, position: payload.position, proposals: payload.proposals_count })
const translateCriterion = (payload: CriterionPayload): Criterion => ({ id: payload.id, name: payload.name, description: payload.description ?? '', weight: payload.weight, position: payload.position })

const GENERAL_KEYS: Array<keyof CfpGeneral> = ['opensOn', 'closesOn', 'resultsOn', 'anonymousReview', 'requireNordesteQuota', 'nordesteQuotaPercent', 'proposalsPerPerson']
const FIELD_FOR: Record<string, string> = {
  opens_at: 'opensOn', closes_at: 'closesOn', results_at: 'resultsOn', nordeste_quota_percent: 'nordesteQuotaPercent', proposals_per_person: 'proposalsPerPerson',
}

function failure(error: unknown, fields: Record<string, string> = {}): MutationResult {
  if (error instanceof CfpSettingsError && error.statusCode === 422 && error.fieldErrors) {
    if (error.fieldErrors.confirm) return { ok: false, needsConfirmation: true }
    const fieldErrors = Object.fromEntries(Object.entries(error.fieldErrors).map(([key, messages]) => [fields[key] ?? key, messages[0] ?? '']))
    return { ok: false, message: Object.values(fieldErrors)[0] ?? locale.genericError, fieldErrors }
  }
  return { ok: false, message: locale.genericError, fieldErrors: {} }
}

export function useCfpSettings(editionId: string) {
  const settings = ref<CfpSettings | null>(null)
  const form = ref<ProposalFormOptions | null>(null)
  const tracks = ref<Track[]>([])
  const criteria = ref<Criterion[]>([])
  const submittedReviews = ref(0)
  const state = ref<'loading' | 'ready' | 'error'>('loading')
  const saving = ref(false)
  const general = reactive<CfpGeneral>({ opensOn: '', closesOn: '', resultsOn: '', anonymousReview: true, requireNordesteQuota: true, nordesteQuotaPercent: 50, proposalsPerPerson: 3 })
  const generalErrors = ref<Record<string, string>>({})

  function acceptSettings(payload: SettingsPayload) {
    settings.value = translateSettings(payload.data)
    form.value = translateForm(payload.meta.form)
    for (const key of GENERAL_KEYS) Object.assign(general, { [key]: settings.value[key] })
  }

  async function loadCriteria() {
    const payload = await cfpSettingsService.criteria(editionId)
    criteria.value = payload.data.map(translateCriterion)
    submittedReviews.value = payload.meta.submitted_reviews
  }

  async function loadTracks() {
    tracks.value = (await cfpSettingsService.tracks(editionId)).data.map(translateTrack)
  }

  async function load(): Promise<void> {
    state.value = 'loading'
    try {
      const [settingsPayload] = await Promise.all([cfpSettingsService.settings(editionId), loadTracks(), loadCriteria()])
      acceptSettings(settingsPayload)
      state.value = 'ready'
    } catch {
      state.value = 'error'
    }
  }

  const generalDirty = computed(() => !!settings.value && GENERAL_KEYS.some((key) => general[key] !== settings.value![key]))

  const windowErrors = computed(() => {
    const errors: Record<string, string> = {}
    if (general.opensOn && general.closesOn && general.closesOn < general.opensOn) errors.closesOn = locale.closesAfterOpens
    if (general.closesOn && general.resultsOn && general.resultsOn < general.closesOn) errors.resultsOn = locale.resultsAfterCloses
    return errors
  })

  async function saveGeneral(): Promise<MutationResult> {
    generalErrors.value = { ...windowErrors.value }
    if (Object.keys(generalErrors.value).length) return { ok: false, message: Object.values(generalErrors.value)[0]!, fieldErrors: generalErrors.value }

    saving.value = true
    try {
      acceptSettings(await cfpSettingsService.updateSettings(editionId, {
        opens_at: startOfDay(general.opensOn),
        closes_at: endOfDay(general.closesOn),
        results_at: endOfDay(general.resultsOn),
        anonymous_review: general.anonymousReview,
        require_nordeste_quota: general.requireNordesteQuota,
        nordeste_quota_percent: Number(general.nordesteQuotaPercent),
        proposals_per_person: Number(general.proposalsPerPerson),
      }))
      return { ok: true }
    } catch (error) {
      const result = failure(error, FIELD_FOR)
      if ('fieldErrors' in result) generalErrors.value = result.fieldErrors
      return result
    } finally {
      saving.value = false
    }
  }

  async function mutate(request: () => Promise<unknown>, reload: () => Promise<void>, fields: Record<string, string> = {}): Promise<MutationResult> {
    try {
      await request()
      await reload()
      return { ok: true }
    } catch (error) {
      return failure(error, fields)
    }
  }

  const saveTrack = (track: Track | null, input: TrackInput) => mutate(
    () => (track ? cfpSettingsService.updateTrack(editionId, track.id, input) : cfpSettingsService.createTrack(editionId, input)),
    loadTracks,
  )
  const removeTrack = (track: Track) => mutate(() => cfpSettingsService.deleteTrack(editionId, track.id), loadTracks)

  async function moveTrack(track: Track, direction: -1 | 1): Promise<MutationResult> {
    const index = tracks.value.findIndex((item) => item.id === track.id)
    const other = tracks.value[index + direction]
    if (!other) return { ok: true }
    return mutate(async () => {
      await cfpSettingsService.updateTrack(editionId, track.id, { position: other.position })
      await cfpSettingsService.updateTrack(editionId, other.id, { position: track.position })
    }, loadTracks)
  }

  const needsImpactConfirmation = computed(() => submittedReviews.value > 0)

  function guardCriteria(changesScores: boolean, confirmed: boolean, request: () => Promise<unknown>): Promise<MutationResult> {
    if (changesScores && needsImpactConfirmation.value && !confirmed) return Promise.resolve({ ok: false, needsConfirmation: true })
    return mutate(request, loadCriteria)
  }

  const changesScores = (criterion: Criterion | null, input: CriterionInput) => !criterion || criterion.weight !== input.weight

  const saveCriterion = (criterion: Criterion | null, input: CriterionInput, confirmed = false) => guardCriteria(changesScores(criterion, input), confirmed, () => {
    const body = { name: input.name, description: input.description || null, weight: input.weight, confirm: confirmed }
    return criterion ? cfpSettingsService.updateCriterion(editionId, criterion.id, body) : cfpSettingsService.createCriterion(editionId, body)
  })
  const removeCriterion = (criterion: Criterion, confirmed = false) => guardCriteria(true, confirmed, () => cfpSettingsService.deleteCriterion(editionId, criterion.id, confirmed))

  const totalWeight = computed(() => criteria.value.reduce((sum, criterion) => sum + criterion.weight, 0))

  return {
    settings, form, tracks, criteria, submittedReviews, state, saving, general, generalErrors, generalDirty, windowErrors, needsImpactConfirmation, totalWeight,
    changesScores, load, saveGeneral, saveTrack, removeTrack, moveTrack, saveCriterion, removeCriterion,
  }
}
