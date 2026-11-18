export interface Option {
  value: string
  label: string
}

export interface CfpInfo {
  status: 'draft' | 'scheduled' | 'open' | 'closed'
  statusLabel: string
  opensAt: string | null
  closesAt: string | null
  anonymousReview: boolean
  proposalsPerPerson: number
  tracks: Option[]
  formats: Option[]
  levels: Option[]
  languages: Option[]
  limits: { title: number, summary: number, audience: number }
}

export interface ProfileForm {
  displayName: string
  jobTitle: string
  company: string
  city: string
  state: string
  bio: string
  github: string
  linkedin: string
  website: string
  accessibilityNeeds: string
  dietaryRestrictions: string
}

export interface TalkForm {
  title: string
  trackId: string
  format: string
  level: string
  language: string
  summary: string
  audience: string
}

export interface Draft {
  step: 1 | 2 | 3
  profile: ProfileForm
  talk: TalkForm
  proposalId: string | null
}

export interface Confirmation {
  status: string
  statusLabel: string
  deadlineAt: string
  respondedAt: string | null
}

export interface Proposal {
  id: string
  number: number
  title: string
  track: string | null
  trackId: string | null
  format: string | null
  formatLabel: string | null
  level: string | null
  levelLabel: string | null
  language: string
  summary: string
  audience: string
  status: 'draft' | 'submitted' | 'withdrawn'
  statusLabel: string
  decision: string | null
  decisionLabel: string | null
  missing: string[]
  isOwner: boolean
  confirmation: Confirmation | null
}

export type Result = { ok: true } | { ok: false, message: string, fieldErrors: Record<string, string> }
