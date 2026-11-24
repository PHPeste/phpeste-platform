import type { AttachmentPayload, CardPayload, ContactPayload, DeliverablePayload, SponsorPayload, SponsorshipPayload, TierPayload } from '../services/sponsorsService'
import { SponsorsError } from '../services/sponsorsError'
import locale from '../locales/pt-br'
import type { Attachment, Contact, Deal, Deliverable, Failure, Sponsor, Tier } from '../types'

export const translateDeliverable = (payload: DeliverablePayload): Deliverable => ({
  id: payload.id,
  title: payload.title,
  ownerId: payload.owner?.id ?? null,
  ownerName: payload.owner?.name ?? null,
  dueOn: payload.due_on,
  status: payload.status,
  statusLabel: payload.status_label,
  overdue: payload.overdue,
})

export const translateDeal = (payload: SponsorshipPayload | CardPayload, sponsorName = ''): Deal => ({
  id: payload.id,
  sponsorId: payload.sponsor.id,
  sponsorName: payload.sponsor.name ?? sponsorName,
  tierId: payload.tier?.id ?? null,
  tierName: payload.tier?.name ?? null,
  stage: payload.stage,
  stageLabel: payload.stage_label,
  valueCents: payload.value.amount,
  receivedCents: payload.received.amount,
  outstandingCents: payload.outstanding.amount,
  ownerId: payload.owner?.id ?? null,
  ownerName: payload.owner?.name ?? null,
  lostReason: payload.lost_reason,
  position: payload.position,
  paidAt: payload.paid_at,
  lastContact: 'last_contact' in payload && payload.last_contact ? { summary: payload.last_contact.summary, at: payload.last_contact.happened_at } : null,
  payments: (payload.payments ?? []).map((payment) => ({
    id: payment.id,
    amountCents: payment.amount.amount,
    paidOn: payment.paid_on,
    note: payment.note,
    recordedBy: payment.recorded_by?.name ?? null,
  })),
  deliverables: (payload.deliverables ?? []).map(translateDeliverable),
})

export const translateTier = (payload: TierPayload): Tier => ({
  id: payload.id,
  name: payload.name,
  priceCents: payload.price.amount,
  slots: payload.slots,
  slotsTaken: payload.slots_taken,
  benefits: payload.benefits,
})

const translateContact = (payload: ContactPayload): Contact => ({
  id: payload.id,
  channelLabel: payload.channel_label,
  at: payload.happened_at,
  summary: payload.summary,
  authorName: payload.author?.name ?? null,
})

const translateAttachment = (payload: AttachmentPayload): Attachment => ({
  id: payload.id,
  kind: payload.kind,
  kindLabel: payload.kind_label,
  name: payload.name,
  sizeBytes: payload.size_bytes,
  uploadedBy: payload.uploaded_by?.name ?? null,
  createdAt: payload.created_at,
})

export const translateSponsor = (payload: SponsorPayload): Sponsor => ({
  id: payload.id,
  name: payload.name,
  website: payload.website,
  contactName: payload.contact.name,
  contactEmail: payload.contact.email,
  contactPhone: payload.contact.phone,
  notes: payload.notes,
  deals: (payload.sponsorships ?? []).map((deal) => translateDeal(deal, payload.name)),
  contacts: (payload.contacts ?? []).map(translateContact),
  attachments: (payload.attachments ?? []).map(translateAttachment),
})

const FIELDS: Record<string, string> = {
  name: 'name', website: 'website', contact_name: 'contactName', contact_email: 'contactEmail', contact_phone: 'contactPhone', notes: 'notes',
  sponsor_tier_id: 'tierId', value_cents: 'value', owner_id: 'ownerId', stage: 'stage', lost_reason: 'lostReason', confirm: 'confirm',
  price_cents: 'price', slots: 'slots', benefits: 'benefits', goal_cents: 'goal',
  amount_cents: 'amount', paid_on: 'paidOn', note: 'note', channel: 'channel', happened_at: 'happenedAt', summary: 'summary',
  title: 'title', due_on: 'dueOn', status: 'status', kind: 'kind', file: 'file',
}

export function failure(error: unknown): Failure {
  if (error instanceof SponsorsError && error.statusCode === 422 && error.fieldErrors) {
    const fieldErrors = Object.fromEntries(Object.entries(error.fieldErrors).map(([key, messages]) => [FIELDS[key.split('.')[0] ?? key] ?? key, messages[0] ?? '']))
    return { ok: false, message: Object.values(fieldErrors)[0] ?? locale.genericError, fieldErrors }
  }
  if (error instanceof SponsorsError && error.statusCode === 409) {
    return { ok: false, message: error.message || locale.genericError, fieldErrors: {} }
  }
  return { ok: false, message: locale.genericError, fieldErrors: {} }
}

export const blank = (text: string) => (text.trim() === '' ? null : text.trim())
