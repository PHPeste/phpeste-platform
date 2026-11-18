import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { ForgotPasswordError } from './forgotPasswordError'
import type { ForgotPasswordInput } from '../types'

const FORGOT_URL = '/auth/password/forgot'

export interface ForgotPasswordPayload {
  sent: boolean
  message: string
}

export default {
  async requestLink(input: ForgotPasswordInput): Promise<ForgotPasswordPayload> {
    try {
      return await httpClient.post<ForgotPasswordPayload>(FORGOT_URL, { body: { email: input.email } })
    } catch (error) {
      throw new ForgotPasswordError(error instanceof Error ? error.message : 'Request failed', error instanceof HttpClientError ? error.statusCode : null)
    }
  },
}
