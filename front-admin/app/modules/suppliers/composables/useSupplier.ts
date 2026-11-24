import { ref } from 'vue'
import suppliersService, { type ChannelValue } from '../services/suppliersService'
import { blank, failure, translateLink } from './translate'
import type { AttachmentKind, Contact, Member, MutationResult, QuoteInput, SupplierInput, SupplierLink } from '../types'

export function useSupplier(editionId: string, linkId: string) {
  const supplier = ref<SupplierLink | null>(null)
  const members = ref<Member[]>([])
  const state = ref<'loading' | 'ready' | 'error' | 'missing'>('loading')

  async function refresh() {
    supplier.value = translateLink((await suppliersService.show(editionId, linkId)).data)
  }

  async function load() {
    state.value = 'loading'
    try {
      await refresh()
      state.value = 'ready'
    } catch (error) {
      state.value = (error as { statusCode?: number }).statusCode === 404 ? 'missing' : 'error'
    }
    suppliersService.team(editionId)
      .then((team) => { members.value = team.data.members.map((member) => ({ id: member.user_id, name: member.name ?? member.email ?? '' })) })
      .catch(() => { members.value = [] })
  }

  async function mutate(request: () => Promise<unknown>): Promise<MutationResult> {
    try {
      await request()
      await refresh()
      return { ok: true }
    } catch (error) {
      return failure(error)
    }
  }

  const save = (input: SupplierInput) => mutate(() => suppliersService.update(editionId, linkId, {
    name: input.name.trim(),
    service: input.service,
    description: blank(input.description),
    document: blank(input.document),
    website: blank(input.website),
    city: blank(input.city),
    state: blank(input.state)?.toUpperCase() ?? null,
    owner_id: input.ownerId || null,
  }))

  /** Contratar com o orçamento aceito, ou só com o valor quando não há orçamento. */
  const hire = (quoteId: string | null, contractedCents: number | null) =>
    mutate(() => suppliersService.changeStatus(editionId, linkId, { status: 'hired', ...(quoteId ? { quote_id: quoteId } : {}), ...(contractedCents === null ? {} : { contracted_cents: contractedCents }) }))
  const decline = (reason: string) => mutate(() => suppliersService.changeStatus(editionId, linkId, { status: 'declined', reason: reason.trim() }))
  const reopen = () => mutate(() => suppliersService.changeStatus(editionId, linkId, { status: 'quoting' }))

  const recordQuote = (input: QuoteInput) => mutate(() => suppliersService.recordQuote(editionId, linkId, {
    amount_cents: input.amountCents,
    description: input.description.trim(),
    received_on: input.receivedOn,
    valid_until: input.validUntil || null,
  }))
  const rejectQuote = (quoteId: string) => mutate(() => suppliersService.rejectQuote(editionId, linkId, quoteId))
  const deleteQuote = (quoteId: string) => mutate(() => suppliersService.deleteQuote(editionId, linkId, quoteId))

  const addContact = (contact: Omit<Contact, 'id'>) => mutate(() => suppliersService.addContact(editionId, linkId, {
    name: contact.name.trim(), role: blank(contact.role ?? ''), email: blank(contact.email ?? ''), phone: blank(contact.phone ?? ''),
  }))
  const deleteContact = (contactId: string) => mutate(() => suppliersService.deleteContact(editionId, linkId, contactId))

  const logActivity = (channel: ChannelValue, happenedAt: string, summary: string) =>
    mutate(() => suppliersService.logActivity(editionId, linkId, { channel, happened_at: happenedAt ? new Date(happenedAt).toISOString() : null, summary: summary.trim() }))

  const upload = (kind: AttachmentKind, file: File) => mutate(() => suppliersService.upload(editionId, linkId, kind, file))
  const deleteAttachment = (attachmentId: string) => mutate(() => suppliersService.deleteAttachment(editionId, attachmentId))

  /** URL assinada pedida só no clique; expira em poucos minutos. */
  async function attachmentUrl(attachmentId: string): Promise<string | null> {
    try {
      return (await suppliersService.attachmentUrl(editionId, attachmentId)).data.url
    } catch {
      return null
    }
  }

  async function remove(): Promise<MutationResult> {
    try {
      await suppliersService.remove(editionId, linkId)
      return { ok: true }
    } catch (error) {
      return failure(error)
    }
  }

  return { supplier, members, state, load, save, hire, decline, reopen, recordQuote, rejectQuote, deleteQuote, addContact, deleteContact, logActivity, upload, deleteAttachment, attachmentUrl, remove }
}
