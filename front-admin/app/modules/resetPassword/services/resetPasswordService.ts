import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { ResetPasswordError } from './resetPasswordError'
import type { ResetPasswordInput } from '../types'

const RESET_URL = '/auth/password/reset'

export default {
  async reset(input: ResetPasswordInput): Promise<{ reset: boolean }> {
    try {
      return await httpClient.post<{ reset: boolean }>(RESET_URL, {
        body: {
          email: input.email,
          token: input.token,
          password: input.password,
          password_confirmation: input.passwordConfirmation,
        },
      })
    } catch (error) {
      if (error instanceof HttpClientError) {
        throw new ResetPasswordError(error.message, error.statusCode, error.fieldErrors)
      }
      throw new ResetPasswordError('Request failed')
    }
  },
}
