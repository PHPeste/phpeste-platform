import type { EditionRole } from '@/modules/shell/types'

export interface TeamMember {
  id: string
  userId: string
  name: string
  email: string
  initials: string
  role: EditionRole
  roleLabel: string
  access: string
  lastSeen: string
}

export interface TeamInvitation {
  id: string
  email: string
  name: string | null
  initials: string
  role: EditionRole
  roleLabel: string
  expiresAt: string
}

export interface TeamSummary {
  people: number
  pendingInvitations: number
  byRole: Record<EditionRole, number>
}

export interface RoleInfo {
  id: EditionRole
  label: string
  access: string
  permissions: string[]
}

export interface PermissionInfo {
  id: string
  label: string
}

export interface InviteInput {
  email: string
  name: string
  role: EditionRole
}
