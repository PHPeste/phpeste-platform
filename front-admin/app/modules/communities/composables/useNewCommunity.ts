import { ref } from 'vue'
import communitiesService from '../services/communitiesService'
import { CommunitiesError } from '../services/communitiesError'
import locale from '../locales/pt-br'
import type { NewCommunityInput } from '../types'

export function useNewCommunity() {
  const isSubmitting = ref(false)
  const errorMessage = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string[]> | null>(null)

  async function submit(input: NewCommunityInput): Promise<boolean> {
    isSubmitting.value = true
    errorMessage.value = null
    fieldErrors.value = null

    try {
      await communitiesService.createCommunity(input)
      return true
    } catch (error) {
      if (error instanceof CommunitiesError && error.statusCode === 403) {
        errorMessage.value = locale.verifyEmailFirst
      } else if (error instanceof CommunitiesError && error.statusCode === 422 && error.fieldErrors) {
        fieldErrors.value = error.fieldErrors
      } else {
        errorMessage.value = locale.genericError
      }
      return false
    } finally {
      isSubmitting.value = false
    }
  }

  return { isSubmitting, errorMessage, fieldErrors, submit }
}
