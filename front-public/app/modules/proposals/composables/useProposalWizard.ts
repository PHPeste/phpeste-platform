import { computed, reactive, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import proposalsService, { type ProposalBody } from '../services/proposalsService'
import locale from '../locales/pt-br'
import { failure } from './failure'
import { nullable, translateProfile, translateProposal } from './translate'
import type { Draft, ProfileForm, Proposal, Result, TalkForm } from '../types'

const AUTOSAVE_MS = 1200

const emptyProfile = (): ProfileForm => ({ displayName: '', jobTitle: '', company: '', city: '', state: '', bio: '', github: '', linkedin: '', website: '', accessibilityNeeds: '', dietaryRestrictions: '' })
const emptyTalk = (): TalkForm => ({ title: '', trackId: '', format: '', level: '', language: 'pt_BR', summary: '', audience: '' })

export const draftKey = (editionId: string) => `phpeste.cfp.draft.${editionId}`

function readDraft(editionId: string): Draft | null {
  if (!import.meta.client) return null
  try {
    const raw = localStorage.getItem(draftKey(editionId))
    if (!raw) return null
    const draft = JSON.parse(raw) as Draft
    return { step: draft.step ?? 1, profile: { ...emptyProfile(), ...draft.profile }, talk: { ...emptyTalk(), ...draft.talk }, proposalId: draft.proposalId ?? null }
  } catch {
    return null
  }
}

const talkBody = (talk: TalkForm): ProposalBody => ({
  title: talk.title.trim(),
  track_id: talk.trackId || null,
  format: talk.format || null,
  level: talk.level || null,
  language: talk.language || 'pt_BR',
  summary: nullable(talk.summary),
  audience: nullable(talk.audience),
})

export function useProposalWizard(editionId: string, editId: string | null = null) {
  const auth = useAuthStore()
  const step = ref<1 | 2 | 3>(1)
  const profile = reactive<ProfileForm>(emptyProfile())
  const talk = reactive<TalkForm>(emptyTalk())
  const proposal = ref<Proposal | null>(null)
  const proposalId = ref<string | null>(editId)
  const saveState = ref<'idle' | 'local' | 'saving' | 'saved' | 'error'>('idle')
  const savedAt = ref<Date | null>(null)
  const errors = ref<Record<string, string>>({})
  const message = ref<string | null>(null)
  const busy = ref<'profile' | 'submit' | null>(null)
  const submitted = ref<Proposal | null>(null)
  const loaded = ref(false)
  let timer: ReturnType<typeof setTimeout> | null = null

  const isEditingSubmitted = computed(() => proposal.value?.status === 'submitted')

  function persistLocal() {
    if (!import.meta.client || editId) return
    const draft: Draft = { step: step.value, profile: { ...profile }, talk: { ...talk }, proposalId: proposalId.value }
    localStorage.setItem(draftKey(editionId), JSON.stringify(draft))
  }

  function clearLocal() {
    if (import.meta.client) localStorage.removeItem(draftKey(editionId))
  }

  async function loadProfile() {
    try {
      const loadedProfile = translateProfile((await proposalsService.profile()).data)
      for (const key of Object.keys(loadedProfile) as Array<keyof ProfileForm>) {
        if (!profile[key]) profile[key] = loadedProfile[key]
      }
    } catch {
      return
    }
  }

  async function load(): Promise<void> {
    const draft = editId ? null : readDraft(editionId)
    if (draft) {
      Object.assign(profile, draft.profile)
      Object.assign(talk, draft.talk)
      step.value = draft.step
      proposalId.value = draft.proposalId
    }

    if (auth.isAuthenticated) {
      await loadProfile()
      if (proposalId.value) {
        try {
          const current = translateProposal((await proposalsService.show(editionId, proposalId.value)).data, auth.user?.id ?? null)
          proposal.value = current
          if (editId || current.status !== 'draft') {
            Object.assign(talk, { title: current.title, trackId: current.trackId ?? '', format: current.format ?? '', level: current.level ?? '', language: current.language, summary: current.summary, audience: current.audience })
            if (editId) step.value = 2
          }
          if (!editId && current.status !== 'draft') {
            proposalId.value = null
            proposal.value = null
            Object.assign(talk, emptyTalk())
            clearLocal()
          }
        } catch {
          if (!editId) proposalId.value = null
        }
      }
    } else if (step.value > 1) {
      step.value = 1
    }

    loaded.value = true
    saveState.value = auth.isAuthenticated ? 'idle' : (talk.title ? 'local' : 'idle')
  }

  async function saveTalk(): Promise<Result> {
    persistLocal()
    if (!auth.isAuthenticated || !talk.title.trim()) {
      saveState.value = 'local'
      return { ok: true }
    }

    saveState.value = 'saving'
    try {
      const response = proposalId.value
        ? await proposalsService.update(editionId, proposalId.value, talkBody(talk))
        : await proposalsService.create(editionId, talkBody(talk))
      proposal.value = translateProposal(response.data, auth.user?.id ?? null)
      proposalId.value = proposal.value.id
      persistLocal()
      saveState.value = 'saved'
      savedAt.value = new Date()
      return { ok: true }
    } catch (error) {
      saveState.value = 'error'
      const result = failure(error)
      errors.value = { ...errors.value, ...result.fieldErrors }
      return result
    }
  }

  function scheduleSave() {
    persistLocal()
    if (!loaded.value || isEditingSubmitted.value) return
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => { saveTalk() }, AUTOSAVE_MS)
  }

  watch(talk, scheduleSave, { deep: true })
  watch(profile, () => persistLocal(), { deep: true })
  watch(step, () => persistLocal())

  function validateProfile(): boolean {
    errors.value = {}
    if (!profile.displayName.trim()) errors.value.displayName = locale.required
    if (!profile.state) errors.value.state = locale.required
    return !Object.keys(errors.value).length
  }

  async function saveProfile(): Promise<Result> {
    if (!validateProfile()) return { ok: false, message: locale.required, fieldErrors: errors.value }
    busy.value = 'profile'
    try {
      await proposalsService.saveProfile({
        display_name: profile.displayName.trim(),
        job_title: nullable(profile.jobTitle),
        company: nullable(profile.company),
        bio: nullable(profile.bio),
        city: nullable(profile.city),
        state: profile.state || null,
        links: { github: nullable(profile.github), linkedin: nullable(profile.linkedin), website: nullable(profile.website) },
        accessibility_needs: nullable(profile.accessibilityNeeds),
        dietary_restrictions: nullable(profile.dietaryRestrictions),
      })
      return { ok: true }
    } catch (error) {
      const result = failure(error)
      errors.value = result.fieldErrors
      return result
    } finally {
      busy.value = null
    }
  }

  const missing = computed(() => {
    const list: string[] = []
    if (!talk.title.trim()) list.push('title')
    if (!talk.trackId) list.push('track_id')
    if (!talk.format) list.push('format')
    if (!talk.level) list.push('level')
    if (!talk.summary.trim()) list.push('summary')
    if (!talk.audience.trim()) list.push('audience')
    return list
  })

  function validateTalk(): boolean {
    errors.value = {}
    const keys: Record<string, string> = { track_id: 'trackId' }
    for (const field of missing.value) errors.value[keys[field] ?? field] = locale.required
    return !missing.value.length
  }

  async function next(): Promise<boolean> {
    message.value = null
    if (step.value === 1) {
      const result = await saveProfile()
      if (!result.ok) {
        message.value = Object.keys(result.fieldErrors).length ? null : result.message
        return false
      }
      step.value = 2
      return true
    }
    if (step.value === 2) {
      if (!validateTalk()) return false
      if (timer) clearTimeout(timer)
      const result = await saveTalk()
      if (!result.ok) {
        message.value = Object.keys(result.fieldErrors).length ? null : result.message
        return false
      }
      step.value = 3
      return true
    }
    return true
  }

  function back() {
    if (step.value > 1) step.value = (step.value - 1) as 1 | 2
  }

  async function submit(): Promise<boolean> {
    message.value = null
    if (!validateTalk()) {
      step.value = 2
      return false
    }
    busy.value = 'submit'
    try {
      if (timer) clearTimeout(timer)
      const saved = await saveTalk()
      if (!saved.ok || !proposalId.value) {
        message.value = saved.ok ? locale.genericError : saved.message
        return false
      }
      const response = isEditingSubmitted.value
        ? await proposalsService.update(editionId, proposalId.value, talkBody(talk))
        : await proposalsService.submit(editionId, proposalId.value)
      submitted.value = translateProposal(response.data, auth.user?.id ?? null)
      clearLocal()
      return true
    } catch (error) {
      const result = failure(error)
      errors.value = result.fieldErrors
      message.value = result.message
      if (result.fieldErrors.speaker_profile) step.value = 1
      return false
    } finally {
      busy.value = null
    }
  }

  function startOver() {
    submitted.value = null
    proposal.value = null
    proposalId.value = null
    Object.assign(talk, emptyTalk())
    step.value = auth.isAuthenticated ? 2 : 1
    saveState.value = 'idle'
    clearLocal()
  }

  async function afterLogin() {
    await loadProfile()
    if (talk.title.trim()) await saveTalk()
  }

  return {
    step, profile, talk, proposal, proposalId, saveState, savedAt, errors, message, busy, submitted, loaded, missing, isEditingSubmitted,
    load, next, back, submit, saveTalk, startOver, afterLogin,
  }
}
