import { computed, ref } from 'vue'
import type { EditionRole } from '@/modules/shell/types'
import teamService, { type InvitationPayload, type MemberPayload, type RolesPayload, type TeamPayload } from '../services/teamService'
import { TeamError } from '../services/teamError'
import locale from '../locales/pt-br'
import { initials, lastSeenLabel } from './format'
import type { InviteInput, PermissionInfo, RoleInfo, TeamInvitation, TeamMember, TeamSummary } from '../types'

const ROLES: EditionRole[] = ['organizador', 'financeiro', 'avaliador', 'checkin', 'voluntario']
const asRole = (value: string): EditionRole => ROLES.find((role) => role === value) ?? 'voluntario'

function translateMember(payload: MemberPayload): TeamMember {
  const email = payload.email ?? ''
  return {
    id: payload.id,
    userId: payload.user_id,
    name: payload.name ?? email,
    email,
    initials: initials(payload.name, email),
    role: asRole(payload.role),
    roleLabel: payload.role_label,
    access: payload.access,
    lastSeen: lastSeenLabel(payload.last_seen_at),
  }
}

function translateInvitation(payload: InvitationPayload): TeamInvitation {
  return {
    id: payload.id,
    email: payload.email,
    name: payload.name,
    initials: initials(payload.name, payload.email),
    role: asRole(payload.role),
    roleLabel: payload.role_label,
    expiresAt: payload.expires_at,
  }
}

function translateSummary(payload: TeamPayload['data']['summary']): TeamSummary {
  return {
    people: payload.people,
    pendingInvitations: payload.pending_invitations,
    byRole: Object.fromEntries(ROLES.map((role) => [role, payload.by_role[role] ?? 0])) as Record<EditionRole, number>,
  }
}

function translateRoles(payload: RolesPayload['data']): { roles: RoleInfo[], permissions: PermissionInfo[] } {
  return {
    roles: payload.roles.map((role) => ({ id: asRole(role.id), label: role.label, access: role.access, permissions: role.permissions })),
    permissions: payload.permissions,
  }
}

export function messageFor(error: unknown): string {
  if (error instanceof TeamError && error.statusCode === 422 && error.fieldErrors) {
    return Object.values(error.fieldErrors).flat()[0] ?? locale.genericError
  }
  return locale.genericError
}

export function useTeam(editionId: string) {
  const members = ref<TeamMember[]>([])
  const invitations = ref<TeamInvitation[]>([])
  const summary = ref<TeamSummary | null>(null)
  const roles = ref<RoleInfo[]>([])
  const permissions = ref<PermissionInfo[]>([])
  const state = ref<'loading' | 'ready' | 'error'>('loading')

  async function refresh(): Promise<void> {
    const { data } = await teamService.list(editionId)
    members.value = data.members.map(translateMember)
    invitations.value = data.invitations.map(translateInvitation)
    summary.value = translateSummary(data.summary)
  }

  async function load(): Promise<void> {
    state.value = 'loading'

    try {
      const [, rolesPayload] = await Promise.all([refresh(), teamService.roles()])
      const translated = translateRoles(rolesPayload.data)
      roles.value = translated.roles
      permissions.value = translated.permissions
      state.value = 'ready'
    } catch {
      state.value = 'error'
    }
  }

  const roleOptions = computed(() => roles.value.map((role) => ({ value: role.id, label: `${role.label} · ${role.access}` })))

  async function mutate(request: () => Promise<unknown>): Promise<string | null> {
    try {
      await request()
      await refresh()
      return null
    } catch (error) {
      return messageFor(error)
    }
  }

  return {
    members, invitations, summary, roles, permissions, state, roleOptions, load,
    invite: (input: InviteInput) => mutate(() => teamService.invite(editionId, input)),
    resend: (invitation: TeamInvitation) => mutate(() => teamService.resendInvitation(editionId, invitation.id)),
    cancel: (invitation: TeamInvitation) => mutate(() => teamService.cancelInvitation(editionId, invitation.id)),
    changeRole: (member: TeamMember, role: EditionRole) => mutate(() => teamService.changeRole(editionId, member.id, role)),
    remove: (member: TeamMember) => mutate(() => teamService.removeMember(editionId, member.id)),
  }
}
