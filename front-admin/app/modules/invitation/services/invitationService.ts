import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import type { AuthUserPayload } from '@/application/shared/composables/useAuthSubmit'
import { InvitationError } from './invitationError'
import type { AcceptInvitationInput } from '../types'

const invitationUrl = (token: string) => `/invitations/${encodeURIComponent(token)}`

export interface InvitationPayload {
  data: {
    edition: { id: string, name: string }
    email: string
    name: string | null
    role: string
    role_label: string
    invited_by: string | null
    account_exists: boolean
    expires_at: string
  }
}

export interface AcceptInvitationPayload {
  user: Pick<AuthUserPayload, 'id' | 'name' | 'email'>
  edition_id: string
  token?: string
}

function wrap(error: unknown): never {
  if (error instanceof HttpClientError) {
    throw new InvitationError(error.message, error.statusCode, error.fieldErrors)
  }
  throw new InvitationError('Request failed')
}

export default {
  async show(token: string): Promise<InvitationPayload> {
    try {
      return await httpClient.get<InvitationPayload>(invitationUrl(token))
    } catch (error) {
      wrap(error)
    }
  },

  async accept(input: AcceptInvitationInput): Promise<AcceptInvitationPayload> {
    try {
      return await httpClient.post<AcceptInvitationPayload>(`${invitationUrl(input.token)}/accept`, {
        body: {
          name: input.name,
          password: input.password,
          password_confirmation: input.passwordConfirmation,
          device_name: 'Painel PHPeste',
        },
      })
    } catch (error) {
      wrap(error)
    }
  },
}
