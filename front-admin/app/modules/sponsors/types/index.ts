import type { AttachmentKindValue, ContactChannelValue, DeliverableStatusValue, StageValue } from '../services/sponsorsService'

export type Stage = StageValue
export type DeliverableStatus = DeliverableStatusValue
export type ContactChannel = ContactChannelValue
export type AttachmentKind = AttachmentKindValue

export interface Indicators {
  goalCents: number | null
  committedCents: number
  receivedCents: number
  pipelineCents: number
  deliverablesOverdue: number
}

export interface Deliverable {
  id: string
  title: string
  ownerId: string | null
  ownerName: string | null
  dueOn: string | null
  status: DeliverableStatus
  statusLabel: string
  overdue: boolean
}

export interface Payment {
  id: string
  amountCents: number
  paidOn: string
  note: string | null
  recordedBy: string | null
}

export interface Deal {
  id: string
  sponsorId: string
  sponsorName: string
  tierId: string | null
  tierName: string | null
  stage: Stage
  stageLabel: string
  valueCents: number
  receivedCents: number
  outstandingCents: number
  ownerId: string | null
  ownerName: string | null
  lostReason: string | null
  position: number
  paidAt: string | null
  lastContact: { summary: string, at: string } | null
  payments: Payment[]
  deliverables: Deliverable[]
}

export interface Column {
  stage: Stage
  label: string
  valueCents: number
  cards: Deal[]
}

export interface Tier {
  id: string
  name: string
  priceCents: number
  slots: number | null
  slotsTaken: number
  benefits: string[]
}

export interface Contact {
  id: string
  channelLabel: string
  at: string
  summary: string
  authorName: string | null
}

export interface Attachment {
  id: string
  kind: AttachmentKind
  kindLabel: string
  name: string
  sizeBytes: number
  uploadedBy: string | null
  createdAt: string
}

export interface Sponsor {
  id: string
  name: string
  website: string | null
  contactName: string | null
  contactEmail: string | null
  contactPhone: string | null
  notes: string | null
  deals: Deal[]
  contacts: Contact[]
  attachments: Attachment[]
}

export interface Member { id: string, name: string }

export interface SponsorInput {
  name: string
  website: string
  contactName: string
  contactEmail: string
  contactPhone: string
  notes: string
}

export interface DealInput { tierId: string, valueCents: number | null, ownerId: string }

export interface NewSponsorInput extends DealInput { name: string, contactName: string, contactEmail: string }

export interface TierInput { name: string, priceCents: number, slots: number | null, benefits: string[] }

export interface DeliverableInput { title: string, ownerId: string, dueOn: string }

export type Failure = { ok: false, message: string, fieldErrors: Record<string, string> }

export type MutationResult = { ok: true } | Failure

/** Mover pode pedir confirmação (cota sem vaga) em vez de falhar de vez. */
export type MoveResult = { ok: true } | (Failure & { needsConfirm: boolean })
