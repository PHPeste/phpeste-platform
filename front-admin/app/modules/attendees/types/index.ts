import type { FilterValue, MissingField } from '../services/attendeesService'

export type { FilterValue, MissingField }

export interface Attendee {
  id: string
  code: string
  active: boolean
  statusLabel: string
  orderNumber: number | null
  ticketTypeName: string
  name: string
  email: string
  badgeName: string | null
  company: string | null
  tshirtSize: string | null
  /** `undefined` quando quem vê não pode ver acessibilidade (financeiro). */
  accessibility: string | null | undefined
  missing: MissingField[]
  transferredFrom: string | null
  lastSentAt: string | null
}

export interface Transfer {
  fromName: string
  toName: string
  byOrganizer: boolean
  at: string
}

export interface AttendeeEdit {
  id: string
  changes: Array<{ field: string, label: string, from: string | null, to: string | null }>
  actorName: string | null
  at: string
}

export interface AttendeeDetail {
  attendee: Attendee
  transfers: Transfer[]
  edits: AttendeeEdit[]
}

export interface AttendeesSummary {
  participants: number
  complete: number
  missing: number
  accessibility: number | null
  transferred: number
}

export interface AttendeeInput {
  name: string
  badgeName: string
  company: string
  tshirtSize: string
  accessibility: string
}

export interface ReminderPreview {
  recipients: number
  subject: string | null
  html: string | null
}

export type MutationResult = { ok: true } | { ok: false, message: string, fieldErrors: Record<string, string> }
