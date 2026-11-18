import { ref } from 'vue'
import forgotPasswordService from '../services/forgotPasswordService'
import { ForgotPasswordError } from '../services/forgotPasswordError'
import locale from '../locales/pt-br'

export function useForgotPassword() {
  const isSubmitting = ref(false)
  const sent = ref(false)
  const errorMessage = ref<string | null>(null)

  async function submit(email: string): Promise<void> {
    isSubmitting.value = true
    errorMessage.value = null

    try {
      await forgotPasswordService.requestLink({ email })
      sent.value = true
    } catch (error) {
      if (error instanceof ForgotPasswordError && error.statusCode === 429) {
        errorMessage.value = locale.tooManyAttempts
      } else if (error instanceof ForgotPasswordError && error.statusCode === 422) {
        sent.value = true
      } else {
        errorMessage.value = locale.genericError
      }
    } finally {
      isSubmitting.value = false
    }
  }

  return { isSubmitting, sent, errorMessage, submit }
}
