import type { EditionRole } from '@/modules/shell/types'

export type BadgeTone = 'ok' | 'warn' | 'danger' | 'info' | 'neutral'
export interface EditionSummary {
  id: string
  name: string
  slug: string
  status: string
  statusLabel: string
  statusTone: BadgeTone
  dates: string
  place: string
  isClosed: boolean
  role: EditionRole | null
}

export interface CommunitySummary {
  id: string
  name: string
  slug: string
  initials: string
  canManage: boolean
  roleLabel: string | null
  editions: EditionSummary[]
}

export interface NewCommunityInput {
  name: string
  slug: string
}

export interface NewEditionInput {
  name: string
  slug: string
  startsOn: string
  endsOn: string
  city: string
  state: string
}

export interface CloneOption {
  id: string
  label: string
}

export interface CloneInput {
  sourceEditionId: string
  name: string
  slug: string
  parts: string[]
}
