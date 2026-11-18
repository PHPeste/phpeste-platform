import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { TeamError } from './teamError'
import type { InviteInput } from '../types'

export interface MemberPayload {
  id: string
  user_id: string
  name: string | null
  email: string | null
  role: string
  role_label: string
  access: string
  last_seen_at: string | null
  joined_at: string
}

export interface InvitationPayload {
  id: string
  email: string
  name: string | null
  role: string
  role_label: string
  expires_at: string
  created_at: string
}

export interface TeamPayload {
  data: {
    members: MemberPayload[]
    invitations: InvitationPayload[]
    summary: { people: number, pending_invitations: number, by_role: Record<string, number> }
  }
}

export interface RolesPayload {
  data: {
    roles: Array<{ id: string, label: string, access: string, permissions: string[] }>
    permissions: Array<{ id: string, label: string }>
  }
}

const teamUrl = (editionId: string) => `/editions/${editionId}/team`

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new TeamError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new TeamError('Request failed')
  }
}

export default {
  list: (editionId: string) => call(() => httpClient.get<TeamPayload>(teamUrl(editionId))),
  roles: () => call(() => httpClient.get<RolesPayload>('/roles')),
  invite: (editionId: string, input: InviteInput) =>
    call(() => httpClient.post<{ data: InvitationPayload }>(`${teamUrl(editionId)}/invitations`, {
      body: { email: input.email, name: input.name || null, role: input.role },
    })),
  resendInvitation: (editionId: string, invitationId: string) =>
    call(() => httpClient.post<{ data: InvitationPayload }>(`${teamUrl(editionId)}/invitations/${invitationId}/resend`)),
  cancelInvitation: (editionId: string, invitationId: string) =>
    call(() => httpClient.delete<null>(`${teamUrl(editionId)}/invitations/${invitationId}`)),
  changeRole: (editionId: string, memberId: string, role: string) =>
    call(() => httpClient.patch<{ data: MemberPayload }>(`${teamUrl(editionId)}/members/${memberId}`, { body: { role } })),
  removeMember: (editionId: string, memberId: string) =>
    call(() => httpClient.delete<null>(`${teamUrl(editionId)}/members/${memberId}`)),
}
