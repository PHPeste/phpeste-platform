import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { AttendeesError } from './attendeesError'

export type MissingField = 'badge_name' | 'tshirt_size'

export interface TicketPayload {
  id: string
  code: string
  status: 'active' | 'cancelled'
  status_label: string
  order_id: string
  order_number: number | null
  ticket_type_id: string
  ticket_type_name: string | null
  batch_name: string | null
  holder: {
    name: string
    email: string
    badge_name: string | null
    company: string | null
    tshirt_size: string | null
    accessibility?: string | null
  }
  missing: MissingField[]
  transferred_from: string | null
  issued_at: string | null
  cancelled_at: string | null
  last_sent_at: string | null
  transfers?: Array<{ from: { name: string, email: string }, to: { name: string, email: string }, channel: string, transferred_by: string | null, at: string }>
  edits?: Array<{ id: string, changes: Array<{ field: string, label: string, from: string | null, to: string | null }>, actor: { id: string, name: string | null } | null, at: string }>
}

export interface SummaryPayload {
  participants: number
  complete: number
  missing: number
  accessibility: number | null
  transferred: number
}

export interface TicketsPage {
  data: TicketPayload[]
  meta: { current_page: number, last_page: number, total: number, summary?: SummaryPayload }
}

export type FilterValue = '' | 'missing' | 'accessibility' | 'transferred'

export interface ListQuery { page: number, filter: FilterValue, q: string }

export interface AttendeeBody {
  holder_name?: string
  badge_name?: string | null
  company?: string | null
  tshirt_size?: string | null
  accessibility?: string | null
}

export interface ReminderPreviewPayload {
  recipients: number
  email: { subject: string, html: string, text: string } | null
}

const base = (editionId: string) => `/editions/${editionId}/ticketing/tickets`

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new AttendeesError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new AttendeesError('Request failed')
  }
}

export default {
  list: (editionId: string, query: ListQuery) => call(() => httpClient.get<TicketsPage>(base(editionId), {
    query: {
      page: query.page,
      ...(query.filter ? { [query.filter]: 1 } : {}),
      ...(query.q.trim() ? { q: query.q.trim() } : {}),
    },
  })),
  show: (editionId: string, ticketId: string) => call(() => httpClient.get<{ data: TicketPayload }>(`${base(editionId)}/${ticketId}`)),
  update: (editionId: string, ticketId: string, body: AttendeeBody) => call(() => httpClient.patch<{ data: TicketPayload }>(`${base(editionId)}/${ticketId}`, { body })),
  resend: (editionId: string, ticketId: string) => call(() => httpClient.post<{ data: TicketPayload }>(`${base(editionId)}/${ticketId}/resend`)),
  reminderPreview: (editionId: string) => call(() => httpClient.get<{ data: ReminderPreviewPayload }>(`${base(editionId)}/data-reminder`)),
  remind: (editionId: string, recipients: number) => call(() => httpClient.post<{ data: { recipients: number } }>(`${base(editionId)}/data-reminder`, { body: { confirm: true, recipients } })),
  badges: (editionId: string) => call(() => httpClient.get<Blob>(`${base(editionId)}/badges`, { as: 'blob' })),
}
