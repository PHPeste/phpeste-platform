import { httpClient } from '@/infrastructure/http/HttpClient'

const LOGOUT_URL = '/auth/logout'

interface AuthPayload {
  user: { id: string, name: string, email: string, avatar_url: string | null, created_at: string }
  token: string
}

export default {
  async logout(): Promise<void> {
    await httpClient.post(LOGOUT_URL)
  },
  login: (body: { email: string, password: string }) => httpClient.post<AuthPayload>('/auth/login', { body: { ...body, device_name: 'Site da edição' } }),
  register: (body: { name: string, email: string, password: string, password_confirmation: string }) => httpClient.post<{ data: unknown }>('/auth/register', { body }),
  resendVerification: () => httpClient.post<{ sent: boolean }>('/auth/email/verification-notification'),
}
