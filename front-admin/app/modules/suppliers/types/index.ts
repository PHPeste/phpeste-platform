import type { AttachmentKindValue, ChannelValue, QuoteStatusValue, ServiceValue, StatusValue } from '../services/suppliersService'

export type Status = StatusValue
export type Service = ServiceValue
export type QuoteStatus = QuoteStatusValue
export type AttachmentKind = AttachmentKindValue
export type Channel = ChannelValue

export interface Contact { id: string, name: string, role: string | null, email: string | null, phone: string | null }

export interface Quote {
  id: string
  amountCents: number
  description: string
  receivedOn: string
  validUntil: string | null
  expired: boolean
  status: QuoteStatus
  statusLabel: string
  recordedBy: string | null
}

export interface Attachment { id: string, kind: AttachmentKind, kindLabel: string, name: string, sizeBytes: number, uploadedBy: string | null, createdAt: string }

export interface Activity { id: string, kind: string, channelLabel: string | null, summary: string, authorName: string | null, at: string }

export interface SupplierLink {
  id: string
  supplierId: string
  name: string
  service: Service
  serviceLabel: string
  description: string | null
  document: string | null
  website: string | null
  city: string | null
  state: string | null
  contactsVisible: boolean
  contacts: Contact[]
  status: Status
  statusLabel: string
  ownerId: string | null
  ownerName: string | null
  quotedCents: number | null
  contractedCents: number | null
  acceptedQuoteId: string | null
  declineReason: string | null
  quotesCount: number
  quotes: Quote[]
  attachments: Attachment[]
  activities: Activity[]
}

export interface Totals { count: number, quotedCents: number, contractedCents: number, byStatus: Record<Status, number> }

export interface DirectoryEntry {
  id: string
  name: string
  serviceLabel: string
  city: string | null
  state: string | null
  inThisEdition: boolean
  editions: Array<{ name: string, statusLabel: string }>
}

export interface Member { id: string, name: string }

export interface SupplierInput {
  name: string
  service: Service
  description: string
  document: string
  website: string
  city: string
  state: string
  ownerId: string
}

export interface NewSupplierInput extends SupplierInput { contactName: string, contactPhone: string, contactEmail: string }

export interface QuoteInput { amountCents: number, description: string, receivedOn: string, validUntil: string }

export type Failure = { ok: false, message: string, fieldErrors: Record<string, string> }

export type MutationResult = { ok: true } | Failure
