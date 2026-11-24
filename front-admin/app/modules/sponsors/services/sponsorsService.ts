import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { SponsorsError } from './sponsorsError'

export interface Money { amount: number, currency: string }
export interface Person { id: string, name: string }

export type StageValue = 'prospecting' | 'negotiating' | 'closed' | 'paid' | 'lost'
export type DeliverableStatusValue = 'pending' | 'in_progress' | 'done'
export type ContactChannelValue = 'email' | 'phone' | 'whatsapp' | 'meeting' | 'other'
export type AttachmentKindValue = 'contract' | 'logo' | 'other'

export interface DeliverablePayload {
  id: string
  sponsorship_id: string
  title: string
  owner: Person | null
  due_on: string | null
  status: DeliverableStatusValue
  status_label: string
  overdue: boolean
  done_at: string | null
  position: number
}

export interface PaymentPayload {
  id: string
  amount: Money
  paid_on: string
  note: string | null
  recorded_by: Person | null
}

export interface SponsorshipPayload {
  id: string
  sponsor: { id: string, name?: string }
  tier: { id: string, name: string } | null
  stage: StageValue
  stage_label: string
  value: Money
  received: Money
  outstanding: Money
  owner: Person | null
  lost_reason: string | null
  position: number
  closed_at: string | null
  paid_at: string | null
  lost_at: string | null
  created_at: string
  payments?: PaymentPayload[]
  deliverables?: DeliverablePayload[]
}

export interface CardPayload extends SponsorshipPayload {
  last_contact: { channel: ContactChannelValue, channel_label: string, summary: string, happened_at: string } | null
}

export interface BoardPayload {
  indicators: { goal: Money | null, committed: Money, received: Money, pipeline: Money, deliverables_overdue: number }
  columns: Array<{ stage: StageValue, label: string, count: number, value: Money, cards: CardPayload[] }>
}

export interface TierPayload {
  id: string
  name: string
  price: Money
  slots: number | null
  slots_taken: number
  slots_left: number | null
  benefits: string[]
  position: number
}

export interface ContactPayload {
  id: string
  channel: ContactChannelValue
  channel_label: string
  happened_at: string
  summary: string
  author: Person | null
}

export interface AttachmentPayload {
  id: string
  kind: AttachmentKindValue
  kind_label: string
  name: string
  mime_type: string
  size_bytes: number
  uploaded_by: Person | null
  created_at: string
}

export interface SponsorPayload {
  id: string
  name: string
  website: string | null
  contact: { name: string | null, email: string | null, phone: string | null }
  notes: string | null
  created_at: string
  sponsorships?: SponsorshipPayload[]
  contacts?: ContactPayload[]
  attachments?: AttachmentPayload[]
}

export interface MemberPayload { user_id: string, name: string | null, email: string | null }

export interface SponsorBody {
  name?: string
  website?: string | null
  contact_name?: string | null
  contact_email?: string | null
  contact_phone?: string | null
  notes?: string | null
}

export interface SponsorshipBody {
  sponsor_tier_id?: string | null
  value_cents?: number | null
  owner_id?: string | null
  stage?: StageValue
  confirm?: boolean
}

export interface TierBody { name: string, price_cents: number, slots: number | null, benefits: string[] }

export interface MoveBody { stage: StageValue, position?: number | null, lost_reason?: string | null, confirm?: boolean }

export interface DeliverableBody { title?: string, owner_id?: string | null, due_on?: string | null, status?: DeliverableStatusValue }

const base = (editionId: string) => `/editions/${editionId}/sponsors`

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new SponsorsError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new SponsorsError('Request failed')
  }
}

export default {
  board: (editionId: string) => call(() => httpClient.get<{ data: BoardPayload }>(`${base(editionId)}/board`)),
  setGoal: (editionId: string, goalCents: number | null) => call(() => httpClient.patch<{ data: { goal: Money | null } }>(`${base(editionId)}/settings`, { body: { goal_cents: goalCents } })),
  tiers: (editionId: string) => call(() => httpClient.get<{ data: TierPayload[] }>(`${base(editionId)}/tiers`)),
  createTier: (editionId: string, body: TierBody) => call(() => httpClient.post<{ data: TierPayload }>(`${base(editionId)}/tiers`, { body })),
  updateTier: (editionId: string, tierId: string, body: TierBody) => call(() => httpClient.patch<{ data: TierPayload }>(`${base(editionId)}/tiers/${tierId}`, { body })),
  deleteTier: (editionId: string, tierId: string) => call(() => httpClient.delete<null>(`${base(editionId)}/tiers/${tierId}`)),
  team: (editionId: string) => call(() => httpClient.get<{ data: { members: MemberPayload[] } }>(`/editions/${editionId}/team`)),

  createSponsor: (editionId: string, body: SponsorBody) => call(() => httpClient.post<{ data: SponsorPayload }>(`${base(editionId)}/companies`, { body })),
  sponsor: (editionId: string, sponsorId: string) => call(() => httpClient.get<{ data: SponsorPayload }>(`${base(editionId)}/companies/${sponsorId}`)),
  updateSponsor: (editionId: string, sponsorId: string, body: SponsorBody) => call(() => httpClient.patch<{ data: SponsorPayload }>(`${base(editionId)}/companies/${sponsorId}`, { body })),
  logContact: (editionId: string, sponsorId: string, body: { channel: ContactChannelValue, happened_at: string | null, summary: string }) =>
    call(() => httpClient.post<{ data: SponsorPayload }>(`${base(editionId)}/companies/${sponsorId}/contacts`, { body })),
  deleteContact: (editionId: string, sponsorId: string, contactId: string) => call(() => httpClient.delete<null>(`${base(editionId)}/companies/${sponsorId}/contacts/${contactId}`)),
  upload: (editionId: string, sponsorId: string, kind: AttachmentKindValue, file: File) => {
    const body = new FormData()
    body.append('kind', kind)
    body.append('file', file)
    return call(() => httpClient.post<{ data: AttachmentPayload }>(`${base(editionId)}/companies/${sponsorId}/attachments`, { body }))
  },
  attachmentUrl: (editionId: string, attachmentId: string) => call(() => httpClient.get<{ data: { url: string, expires_at: string } }>(`${base(editionId)}/attachments/${attachmentId}/url`)),
  deleteAttachment: (editionId: string, attachmentId: string) => call(() => httpClient.delete<null>(`${base(editionId)}/attachments/${attachmentId}`)),

  createSponsorship: (editionId: string, sponsorId: string, body: SponsorshipBody) =>
    call(() => httpClient.post<{ data: SponsorshipPayload }>(`${base(editionId)}/companies/${sponsorId}/sponsorships`, { body })),
  updateSponsorship: (editionId: string, dealId: string, body: SponsorshipBody) => call(() => httpClient.patch<{ data: SponsorshipPayload }>(`${base(editionId)}/sponsorships/${dealId}`, { body })),
  move: (editionId: string, dealId: string, body: MoveBody) => call(() => httpClient.post<{ data: SponsorshipPayload }>(`${base(editionId)}/sponsorships/${dealId}/move`, { body })),
  recordPayment: (editionId: string, dealId: string, body: { amount_cents: number, paid_on: string, note: string | null }) =>
    call(() => httpClient.post<{ data: SponsorshipPayload }>(`${base(editionId)}/sponsorships/${dealId}/payments`, { body })),
  deletePayment: (editionId: string, dealId: string, paymentId: string) => call(() => httpClient.delete<null>(`${base(editionId)}/sponsorships/${dealId}/payments/${paymentId}`)),
  createDeliverable: (editionId: string, dealId: string, body: DeliverableBody) =>
    call(() => httpClient.post<{ data: DeliverablePayload }>(`${base(editionId)}/sponsorships/${dealId}/deliverables`, { body })),
  updateDeliverable: (editionId: string, deliverableId: string, body: DeliverableBody) =>
    call(() => httpClient.patch<{ data: DeliverablePayload }>(`${base(editionId)}/deliverables/${deliverableId}`, { body })),
  deleteDeliverable: (editionId: string, deliverableId: string) => call(() => httpClient.delete<null>(`${base(editionId)}/deliverables/${deliverableId}`)),
}
