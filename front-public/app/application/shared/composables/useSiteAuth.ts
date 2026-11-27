import { computed } from 'vue'
import { HttpClientError } from '@/infrastructure/http/HttpClient'
import { useAuthSubmit } from '@/application/shared/composables/useAuthSubmit'
import { useAuthStore } from '@/stores/authStore'
import authService from '../services/authService'
import locale from '@/application/locales/pt-br/auth'

function mapError(error: unknown) {
  if (error instanceof HttpClientError && error.statusCode === 401) return { message: locale.invalidCredentials, fieldErrors: null }
  if (error instanceof HttpClientError && error.statusCode === 422 && error.fieldErrors) {
    return { message: Object.values(error.fieldErrors)[0]?.[0] ?? locale.genericError, fieldErrors: error.fieldErrors }
  }
  return { message: locale.genericError, fieldErrors: null }
}

export function useSiteAuth() {
  const auth = useAuthStore()
  const login = useAuthSubmit<{ email: string, password: string }>({ request: (credentials) => authService.login(credentials), mapError })
  const register = useAuthSubmit<{ name: string, email: string, password: string, passwordConfirmation: string }>({
    request: async (input) => {
      await authService.register({ name: input.name, email: input.email, password: input.password, password_confirmation: input.passwordConfirmation })
      return authService.login({ email: input.email, password: input.password })
    },
    mapError,
  })

  return { auth, user: computed(() => auth.user), isAuthenticated: computed(() => auth.isAuthenticated), login, register }
}
