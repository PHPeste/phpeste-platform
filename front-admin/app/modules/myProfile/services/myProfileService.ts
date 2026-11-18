import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { MyProfileError } from './myProfileError'

export interface UserPayload {
  id: string
  name: string
  email: string
  avatar_url: string | null
  locale: string
  has_password: boolean
  providers: string[]
  notifications: Array<{ id: string, label: string, description: string, enabled: boolean }>
}

export interface SessionPayload {
  id: string
  name: string
  current: boolean
  last_used_at: string | null
  created_at: string | null
}

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new MyProfileError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new MyProfileError('Request failed')
  }
}

export default {
  me: () => call(() => httpClient.get<{ user: UserPayload }>('/me')),
  update: (body: { name?: string, locale?: string, notifications?: Record<string, boolean> }) => call(() => httpClient.patch<{ data: UserPayload }>('/me', { body })),
  uploadPhoto: (file: File) => {
    const body = new FormData()
    body.append('photo', file)
    return call(() => httpClient.post<{ data: UserPayload }>('/me/photo', { body }))
  },
  removePhoto: () => call(() => httpClient.delete<{ data: UserPayload }>('/me/photo')),
  changePassword: (body: { current_password: string | null, password: string, password_confirmation: string }) =>
    call(() => httpClient.put<{ updated: boolean }>('/auth/password', { body })),
  sessions: () => call(() => httpClient.get<{ data: SessionPayload[] }>('/me/sessions')),
  endSession: (id: string) => call(() => httpClient.delete<null>(`/me/sessions/${id}`)),
}
