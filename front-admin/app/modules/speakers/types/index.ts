export type SpeakerStatus = 'pending' | 'confirmed' | 'declined' | 'withdrawn' | 'no_response' | 'cancelled' | 'waitlisted'
export type SpeakerFilter = 'all' | 'confirmed' | 'pending' | 'waitlisted'

export interface SpeakerRow {
  userId: string
  name: string
  email: string
  initials: string
  state: string | null
  nordeste: boolean
  proposal: { id: string, number: number, title: string, track: string | null, invited: boolean }
  status: SpeakerStatus
  statusLabel: string
  deadlineAt: string | null
  remindedAt: string | null
  attended: boolean
}

export interface SpeakersSummary {
  confirmed: number
  awaiting: number
  waitlisted: number
  slots: number
  nordestePercent: number | null
  nordesteQuotaPercent: number
}

export interface CoverageCell {
  state: string
  confirmed: number
}

export interface PromotionCandidate {
  proposalId: string
  number: number
  title: string
  finalScore: number | null
}

export interface PromotionGroup {
  track: string
  openSlots: number
  next: PromotionCandidate | null
}

export interface SpeakerTalk {
  id: string
  number: number
  title: string
  track: string | null
  invited: boolean
  format: string | null
  durationMinutes: number | null
  decisionLabel: string | null
  status: SpeakerStatus
  statusLabel: string
  deadlineAt: string | null
  respondedAt: string | null
  remindedAt: string | null
  attendedAt: string | null
  reason: string | null
}

export interface InviteInput {
  name: string
  email: string
  title: string
  trackId: string
  format: string
  summary: string
}

export interface InviteOptions {
  tracks: Array<{ value: string, label: string }>
  formats: Array<{ value: string, label: string }>
}

export interface SpeakerLogistics {
  originCity: string | null
  originState: string | null
  needsTravel: boolean
  needsLodging: boolean
  travelPaidByOrganization: boolean
  lodgingPaidByOrganization: boolean
  organizationNotes: string
  accessibilityNeeds: string | null
  dietaryRestrictions: string | null
}

export interface SpeakerDetail {
  userId: string
  name: string
  email: string
  initials: string
  displayName: string
  jobTitle: string | null
  company: string | null
  bio: string | null
  city: string | null
  state: string | null
  nordeste: boolean
  links: Array<{ label: string, url: string }>
  photoUrl: string | null
  canViewPrivateData: boolean
  talks: SpeakerTalk[]
  logistics: SpeakerLogistics | null
}
