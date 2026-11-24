import type { DirectoryPayload, LinkPayload, TotalsPayload } from '../services/suppliersService'
import { SuppliersError } from '../services/suppliersError'
import locale from '../locales/pt-br'
import type { DirectoryEntry, Failure, SupplierLink, Totals } from '../types'

export const translateLink = (payload: LinkPayload): SupplierLink => ({
  id: payload.id,
  supplierId: payload.supplier.id,
  name: payload.supplier.name,
  service: payload.supplier.service,
  serviceLabel: payload.supplier.service_label,
  description: payload.supplier.description,
  document: payload.supplier.document,
  website: payload.supplier.website,
  city: payload.supplier.city,
  state: payload.supplier.state,
  contactsVisible: payload.supplier.contacts_visible,
  contacts: payload.supplier.contacts ?? [],
  status: payload.status,
  statusLabel: payload.status_label,
  ownerId: payload.owner?.id ?? null,
  ownerName: payload.owner?.name ?? null,
  quotedCents: payload.quoted?.amount ?? null,
  contractedCents: payload.contracted?.amount ?? null,
  acceptedQuoteId: payload.accepted_quote_id,
  declineReason: payload.decline_reason,
  quotesCount: payload.quotes_count,
  quotes: (payload.quotes ?? []).map((quote) => ({
    id: quote.id,
    amountCents: quote.amount.amount,
    description: quote.description,
    receivedOn: quote.received_on,
    validUntil: quote.valid_until,
    expired: quote.expired,
    status: quote.status,
    statusLabel: quote.status_label,
    recordedBy: quote.recorded_by?.name ?? null,
  })),
  attachments: (payload.attachments ?? []).map((attachment) => ({
    id: attachment.id,
    kind: attachment.kind,
    kindLabel: attachment.kind_label,
    name: attachment.name,
    sizeBytes: attachment.size_bytes,
    uploadedBy: attachment.uploaded_by?.name ?? null,
    createdAt: attachment.created_at,
  })),
  activities: (payload.activities ?? []).map((activity) => ({
    id: activity.id,
    kind: activity.kind,
    channelLabel: activity.channel_label,
    summary: activity.summary,
    authorName: activity.author?.name ?? null,
    at: activity.happened_at,
  })),
})

export const translateTotals = (payload: TotalsPayload): Totals => ({
  count: payload.count,
  quotedCents: payload.quoted.amount,
  contractedCents: payload.contracted.amount,
  byStatus: payload.by_status,
})

export const translateDirectory = (payload: DirectoryPayload): DirectoryEntry => ({
  id: payload.id,
  name: payload.name,
  serviceLabel: payload.service_label,
  city: payload.city,
  state: payload.state,
  inThisEdition: payload.in_this_edition,
  editions: payload.editions.map((edition) => ({ name: edition.name, statusLabel: edition.status_label })),
})

/** O que a lista soma, com a mesma regra da API: recusado não entra no orçado, só contratado entra no contratado. */
export function sumRows(rows: SupplierLink[]): { quotedCents: number, contractedCents: number } {
  return rows.reduce((sum, row) => ({
    quotedCents: sum.quotedCents + (row.status === 'declined' ? 0 : row.quotedCents ?? 0),
    contractedCents: sum.contractedCents + (row.status === 'hired' ? row.contractedCents ?? 0 : 0),
  }), { quotedCents: 0, contractedCents: 0 })
}

const FIELDS: Record<string, string> = {
  name: 'name', service: 'service', description: 'description', document: 'document', website: 'website', city: 'city', state: 'state', owner_id: 'ownerId',
  contacts: 'contactName', quote_id: 'quote', contracted_cents: 'contracted', reason: 'reason', status: 'status', supplier: 'supplier', quote: 'quote',
  amount_cents: 'amount', received_on: 'receivedOn', valid_until: 'validUntil', channel: 'channel', happened_at: 'happenedAt', summary: 'summary',
  role: 'role', email: 'email', phone: 'phone', kind: 'kind', file: 'file',
}

export function failure(error: unknown): Failure {
  if (error instanceof SuppliersError && error.statusCode === 422 && error.fieldErrors) {
    const fieldErrors = Object.fromEntries(Object.entries(error.fieldErrors).map(([key, messages]) => [FIELDS[key.split('.')[0] ?? key] ?? key, messages[0] ?? '']))
    return { ok: false, message: Object.values(fieldErrors)[0] ?? locale.genericError, fieldErrors }
  }
  return { ok: false, message: locale.genericError, fieldErrors: {} }
}

export const blank = (text: string) => (text.trim() === '' ? null : text.trim())
