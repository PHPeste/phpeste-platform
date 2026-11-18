import { ref } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import resetPasswordService from '../services/resetPasswordService'
import { ResetPasswordError } from '../services/resetPasswordError'
import locale from '../locales/pt-br'
import type { ResetPasswordInput } from '../types'

export function useResetPassword() {
  const isSubmitting = ref(false)
  const done = ref(false)
  const invalidLink = ref(false)
  const errorMessage = ref<string | null>(null)
  const passwordError = ref<string | null>(null)

  async function submit(input: ResetPasswordInput): Promise<void> {
    isSubmitting.value = true
    errorMessage.value = null
    passwordError.value = null

    try {
      await resetPasswordService.reset(input)
      useAuthStore().clearSession()
      done.value = true
    } catch (error) {
      if (!(error instanceof ResetPasswordError)) {
        errorMessage.value = locale.genericError
      } else if (error.statusCode === 422 && (error.fieldErrors?.token || error.fieldErrors?.email)) {
        invalidLink.value = true
      } else if (error.statusCode === 422 && error.fieldErrors?.password) {
        passwordError.value = error.fieldErrors.password[0] ?? locale.genericError
      } else if (error.statusCode === 429) {
        errorMessage.value = locale.tooManyAttempts
      } else {
        errorMessage.value = locale.genericError
      }
    } finally {
      isSubmitting.value = false
    }
  }

  return { isSubmitting, done, invalidLink, errorMessage, passwordError, submit }
}
