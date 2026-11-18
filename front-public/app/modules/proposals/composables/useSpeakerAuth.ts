import { computed } from 'vue'
import { useAuthSubmit } from '@/application/shared/composables/useAuthSubmit'
import { useAuthStore } from '@/stores/authStore'
import proposalsService from '../services/proposalsService'
import { ProposalsError } from '../services/proposalsError'
import locale from '../locales/pt-br'

function mapError(error: unknown) {
  if (error instanceof ProposalsError && error.statusCode === 401) return { message: locale.invalidCredentials, fieldErrors: null }
  if (error instanceof ProposalsError && error.statusCode === 422 && error.fieldErrors) {
    return { message: Object.values(error.fieldErrors)[0]?.[0] ?? locale.genericError, fieldErrors: error.fieldErrors }
  }
  return { message: locale.genericError, fieldErrors: null }
}

export function useSpeakerAuth() {
  const auth = useAuthStore()
  const login = useAuthSubmit<{ email: string, password: string }>({ request: (credentials) => proposalsService.login(credentials), mapError })
  const register = useAuthSubmit<{ name: string, email: string, password: string, passwordConfirmation: string }>({
    request: async (input) => {
      await proposalsService.register({ name: input.name, email: input.email, password: input.password, password_confirmation: input.passwordConfirmation })
      return proposalsService.login({ email: input.email, password: input.password })
    },
    mapError,
  })

  return { auth, user: computed(() => auth.user), isAuthenticated: computed(() => auth.isAuthenticated), login, register }
}
