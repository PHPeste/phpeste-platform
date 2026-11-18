import { ref } from 'vue'
import communitiesService from '../services/communitiesService'
import { CommunitiesError } from '../services/communitiesError'
import locale from '../locales/pt-br'
import type { CloneInput, CloneOption, NewEditionInput } from '../types'

const POLL_INTERVAL_MS = 1000
const POLL_ATTEMPTS = 60

const wait = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms))

export function useNewEdition() {
  const isSubmitting = ref(false)
  const errorMessage = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string[]> | null>(null)
  const cloneParts = ref<CloneOption[]>([])
  const neverCopied = ref<string[]>([])
  const isCloning = ref(false)

  function fail(error: unknown) {
    if (error instanceof CommunitiesError && error.statusCode === 422 && error.fieldErrors) {
      fieldErrors.value = error.fieldErrors
    } else {
      errorMessage.value = locale.genericError
    }
  }

  async function loadCloneOptions(sourceEditionId: string): Promise<void> {
    try {
      const { data } = await communitiesService.cloneOptions(sourceEditionId)
      cloneParts.value = data.parts.map((part) => ({ id: part.id, label: part.label }))
      neverCopied.value = data.never_copied
    } catch {
      cloneParts.value = []
      neverCopied.value = []
    }
  }

  function reset() {
    errorMessage.value = null
    fieldErrors.value = null
  }

  async function createBlank(communityId: string, input: NewEditionInput): Promise<string | null> {
    isSubmitting.value = true
    reset()

    try {
      const { data } = await communitiesService.createEdition(communityId, input)
      return data.id
    } catch (error) {
      fail(error)
      return null
    } finally {
      isSubmitting.value = false
    }
  }

  async function createFromPrevious(input: CloneInput): Promise<string | null> {
    isSubmitting.value = true
    reset()

    try {
      let { data } = await communitiesService.clone(input)
      isCloning.value = data.status === 'queued'

      for (let attempt = 0; data.status === 'queued' && attempt < POLL_ATTEMPTS; attempt++) {
        await wait(POLL_INTERVAL_MS)
        data = (await communitiesService.cloneStatus(data.id)).data
      }

      if (data.status === 'done' && data.target_edition_id) return data.target_edition_id

      errorMessage.value = data.status === 'failed'
        ? locale.cloneFailed.replace('{motivo}', data.error ?? locale.genericError)
        : locale.cloneTimeout
      return null
    } catch (error) {
      fail(error)
      return null
    } finally {
      isSubmitting.value = false
      isCloning.value = false
    }
  }

  return { isSubmitting, isCloning, errorMessage, fieldErrors, cloneParts, neverCopied, loadCloneOptions, createBlank, createFromPrevious }
}
