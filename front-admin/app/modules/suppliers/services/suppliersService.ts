import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { SuppliersError } from './suppliersError'

export interface Money { amount: number, currency: string }
export interface Person { id: string, name: string }

export type StatusValue = 'to_contact' | 'quoting' | 'hired' | 'declined'
export type ServiceValue = 'venue' | 'catering' | 'audio_visual' | 'printing' | 'merch' | 'transport' | 'accommodation' | 'photo_video' | 'accessibility' | 'other'
export type QuoteStatusValue = 'received' | 'accepted' | 'rejected'
export type AttachmentKindValue = 'quote' | 'contract' | 'invoice' | 'other'
export type ChannelValue = 'email' | 'phone' | 'whatsapp' | 'meeting' | 'visit' | 'other'

export interface ContactPayload { id: string, name: string, role: string | null, email: string | null, phone: string | null }

export interface SupplierPayload {
  id: string
  name: string
  service: ServiceValue
  service_label: string
  description: string | null
  document: string | null
  website: string | null
  city: string | null
  state: string | null
  contacts_visible: boolean
  contacts: ContactPayload[] | null
}

export interface QuotePayload {
  id: string
  amount: Money
  description: string
  received_on: string
  valid_until: string | null
  expired: boolean
  status: QuoteStatusValue
  status_label: string
  recorded_by: Person | null
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

export interface ActivityPayload {
  id: string
  kind: 'note' | 'status' | 'quote' | 'attachment'
  channel: ChannelValue | null
  channel_label: string | null
  summary: string
  author: Person | null
  happened_at: string
}

export interface LinkPayload {
  id: string
  supplier: SupplierPayload
  status: StatusValue
  status_label: string
  owner: Person | null
  quoted: Money | null
  contracted: Money | null
  accepted_quote_id: string | null
  decline_reason: string | null
  hired_at: string | null
  declined_at: string | null
  quotes_count: number
  quotes?: QuotePayload[]
  attachments?: AttachmentPayload[]
  activities?: ActivityPayload[]
}

export interface TotalsPayload { count: number, quoted: Money, contracted: Money, by_status: Record<StatusValue, number> }

export interface DirectoryPayload extends SupplierPayload {
  in_this_edition: boolean
  editions: Array<{ id: string, name: string, status: StatusValue, status_label: string }>
}

export interface MemberPayload { user_id: string, name: string | null, email: string | null }

export interface ListQuery { status: string, service: string, q: string }

export interface SupplierBody {
  name?: string
  service?: ServiceValue
  description?: string | null
  document?: string | null
  website?: string | null
  city?: string | null
  state?: string | null
  owner_id?: string | null
  contacts?: Array<{ name: string, role: string | null, email: string | null, phone: string | null }>
}

export interface StatusBody { status: StatusValue, quote_id?: string | null, contracted_cents?: number | null, reason?: string | null }

const base = (editionId: string) => `/editions/${editionId}/suppliers`

const filters = (query: ListQuery) => ({
  ...(query.status ? { status: query.status } : {}),
  ...(query.service ? { service: query.service } : {}),
  ...(query.q.trim() ? { q: query.q.trim() } : {}),
})

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new SuppliersError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new SuppliersError('Request failed')
  }
}

export default {
  list: (editionId: string, query: ListQuery) => call(() => httpClient.get<{ data: LinkPayload[], totals: TotalsPayload }>(base(editionId), { query: filters(query) })),
  create: (editionId: string, body: SupplierBody) => call(() => httpClient.post<{ data: LinkPayload }>(base(editionId), { body })),
  show: (editionId: string, linkId: string) => call(() => httpClient.get<{ data: LinkPayload }>(`${base(editionId)}/${linkId}`)),
  update: (editionId: string, linkId: string, body: SupplierBody) => call(() => httpClient.patch<{ data: LinkPayload }>(`${base(editionId)}/${linkId}`, { body })),
  remove: (editionId: string, linkId: string) => call(() => httpClient.delete<null>(`${base(editionId)}/${linkId}`)),
  changeStatus: (editionId: string, linkId: string, body: StatusBody) => call(() => httpClient.post<{ data: LinkPayload }>(`${base(editionId)}/${linkId}/status`, { body })),
  logActivity: (editionId: string, linkId: string, body: { channel: ChannelValue, happened_at: string | null, summary: string }) =>
    call(() => httpClient.post<{ data: LinkPayload }>(`${base(editionId)}/${linkId}/activities`, { body })),
  addContact: (editionId: string, linkId: string, body: Omit<ContactPayload, 'id'>) => call(() => httpClient.post<{ data: ContactPayload }>(`${base(editionId)}/${linkId}/contacts`, { body })),
  deleteContact: (editionId: string, linkId: string, contactId: string) => call(() => httpClient.delete<null>(`${base(editionId)}/${linkId}/contacts/${contactId}`)),
  recordQuote: (editionId: string, linkId: string, body: { amount_cents: number, description: string, received_on: string, valid_until: string | null }) =>
    call(() => httpClient.post<{ data: unknown }>(`${base(editionId)}/${linkId}/quotes`, { body })),
  rejectQuote: (editionId: string, linkId: string, quoteId: string) => call(() => httpClient.post<{ data: unknown }>(`${base(editionId)}/${linkId}/quotes/${quoteId}/reject`)),
  deleteQuote: (editionId: string, linkId: string, quoteId: string) => call(() => httpClient.delete<null>(`${base(editionId)}/${linkId}/quotes/${quoteId}`)),
  upload: (editionId: string, linkId: string, kind: AttachmentKindValue, file: File) => {
    const body = new FormData()
    body.append('kind', kind)
    body.append('file', file)
    return call(() => httpClient.post<{ data: AttachmentPayload }>(`${base(editionId)}/${linkId}/attachments`, { body }))
  },
  attachmentUrl: (editionId: string, attachmentId: string) => call(() => httpClient.get<{ data: { url: string, expires_at: string } }>(`${base(editionId)}/attachments/${attachmentId}/url`)),
  deleteAttachment: (editionId: string, attachmentId: string) => call(() => httpClient.delete<null>(`${base(editionId)}/attachments/${attachmentId}`)),
  directory: (editionId: string, q: string) => call(() => httpClient.get<{ data: DirectoryPayload[] }>(`${base(editionId)}/directory`, { query: q.trim() ? { q: q.trim() } : {} })),
  link: (editionId: string, supplierId: string, ownerId: string | null) => call(() => httpClient.post<{ data: LinkPayload }>(`${base(editionId)}/directory/${supplierId}`, { body: { owner_id: ownerId } })),
  team: (editionId: string) => call(() => httpClient.get<{ data: { members: MemberPayload[] } }>(`/editions/${editionId}/team`)),
}
