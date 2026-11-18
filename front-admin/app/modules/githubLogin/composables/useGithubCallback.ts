import { useAuthSubmit } from '@/application/shared/composables/useAuthSubmit'
import githubLoginService from '../services/githubLoginService'
import { GithubLoginError } from '../services/githubLoginError'
import locale from '../locales/pt-br'
import type { GithubCallbackInput } from '../types'

function mapError(error: unknown) {
  if (!(error instanceof GithubLoginError)) {
    return { message: locale.genericError, fieldErrors: null }
  }

  if (error.statusCode === 422 && error.fieldErrors?.state) {
    return { message: locale.expired, fieldErrors: error.fieldErrors }
  }

  if (error.statusCode === 422 && error.fieldErrors) {
    return { message: Object.values(error.fieldErrors)[0]?.[0] ?? locale.genericError, fieldErrors: error.fieldErrors }
  }

  return { message: locale.genericError, fieldErrors: error.fieldErrors }
}

export function useGithubCallback() {
  return useAuthSubmit<GithubCallbackInput>({
    request: (input) => githubLoginService.callback(input),
    mapError,
  })
}
