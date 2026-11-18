import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import type { AuthPayload } from '@/application/shared/composables/useAuthSubmit'
import { GithubLoginError } from './githubLoginError'
import type { GithubCallbackInput } from '../types'

const CALLBACK_URL = '/auth/github/callback'

export default {
  async callback(input: GithubCallbackInput): Promise<AuthPayload> {
    try {
      return await httpClient.post<AuthPayload>(CALLBACK_URL, {
        body: { code: input.code, state: input.state, device_name: 'Painel PHPeste' },
      })
    } catch (error) {
      if (error instanceof HttpClientError) {
        throw new GithubLoginError(error.message, error.statusCode, error.fieldErrors)
      }
      throw new GithubLoginError('Could not log in with GitHub right now.')
    }
  },
}
