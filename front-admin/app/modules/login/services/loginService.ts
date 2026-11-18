import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import type { AuthPayload } from '@/application/shared/composables/useAuthSubmit'
import { LoginError } from './loginError'
import type { LoginCredentials } from '../types'

const LOGIN_URL = '/auth/login'
const GITHUB_REDIRECT_URL = '/auth/github/redirect'

export type LoginPayload = AuthPayload

export interface GithubRedirectPayload {
  url: string
}

function handleError(error: unknown): never {
  if (error instanceof HttpClientError) {
    throw new LoginError(error.message, error.statusCode, error.fieldErrors)
  }

  throw new LoginError('Could not log in right now. Please try again.')
}

// Only talks to the backend — no response translation happens here, that's
// the composable's job. See docs/ARCHITECTURE.md, "services/".
export default {
  async login(credentials: LoginCredentials): Promise<LoginPayload> {
    try {
      return await httpClient.post<LoginPayload>(LOGIN_URL, {
        body: { email: credentials.email, password: credentials.password, device_name: 'Painel PHPeste' },
      })
    } catch (error) {
      handleError(error)
    }
  },

  async githubRedirect(): Promise<GithubRedirectPayload> {
    try {
      return await httpClient.get<GithubRedirectPayload>(GITHUB_REDIRECT_URL)
    } catch (error) {
      handleError(error)
    }
  },
}
