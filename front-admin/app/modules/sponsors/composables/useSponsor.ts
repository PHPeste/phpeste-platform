import { computed, ref } from 'vue'
import sponsorsService, { type ContactChannelValue, type DeliverableStatusValue, type StageValue } from '../services/sponsorsService'
import { blank, failure, translateSponsor, translateTier } from './translate'
import type { AttachmentKind, DealInput, DeliverableInput, Member, MoveResult, MutationResult, Sponsor, SponsorInput, Tier } from '../types'

export function useSponsor(editionId: string, sponsorId: string) {
  const sponsor = ref<Sponsor | null>(null)
  const tiers = ref<Tier[]>([])
  const members = ref<Member[]>([])
  const selectedDealId = ref<string | null>(null)
  const state = ref<'loading' | 'ready' | 'error' | 'missing'>('loading')

  /** O acordo em foco: o escolhido, ou o mais recente que não foi perdido. */
  const deal = computed(() => {
    const deals = sponsor.value?.deals ?? []
    return deals.find((item) => item.id === selectedDealId.value) ?? [...deals].reverse().find((item) => item.stage !== 'lost') ?? deals.at(-1) ?? null
  })

  async function refresh() {
    const [shown, tierList] = await Promise.all([sponsorsService.sponsor(editionId, sponsorId), sponsorsService.tiers(editionId)])
    sponsor.value = translateSponsor(shown.data)
    tiers.value = tierList.data.map(translateTier)
  }

  async function load() {
    state.value = 'loading'
    try {
      await refresh()
      state.value = 'ready'
    } catch (error) {
      state.value = (error as { statusCode?: number }).statusCode === 404 ? 'missing' : 'error'
    }
    sponsorsService.team(editionId)
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

  const dealId = () => deal.value?.id ?? ''

  const saveSponsor = (input: SponsorInput) => mutate(() => sponsorsService.updateSponsor(editionId, sponsorId, {
    name: input.name.trim(),
    website: blank(input.website),
    contact_name: blank(input.contactName),
    contact_email: blank(input.contactEmail),
    contact_phone: blank(input.contactPhone),
    notes: blank(input.notes),
  }))

  const dealBody = (input: DealInput, confirm: boolean) => ({
    sponsor_tier_id: input.tierId || null,
    value_cents: input.valueCents,
    owner_id: input.ownerId || null,
    ...(confirm ? { confirm: true } : {}),
  })

  async function saveDeal(input: DealInput, confirm = false): Promise<MoveResult> {
    const result = await mutate(() => sponsorsService.updateSponsorship(editionId, dealId(), dealBody(input, confirm)))
    return result.ok ? result : { ...result, needsConfirm: 'confirm' in result.fieldErrors }
  }

  async function openDeal(input: DealInput): Promise<MutationResult> {
    let createdId: string | null = null
    const result = await mutate(async () => {
      const created = await sponsorsService.createSponsorship(editionId, sponsorId, { sponsor_tier_id: input.tierId || null, ...(input.valueCents === null ? {} : { value_cents: input.valueCents }), owner_id: input.ownerId || null })
      createdId = created.data.id
    })
    if (createdId) selectedDealId.value = createdId
    return result
  }

  async function move(stage: StageValue, lostReason: string, confirm = false): Promise<MoveResult> {
    const result = await mutate(() => sponsorsService.move(editionId, dealId(), { stage, ...(stage === 'lost' ? { lost_reason: lostReason.trim() } : {}), ...(confirm ? { confirm: true } : {}) }))
    return result.ok ? result : { ...result, needsConfirm: 'confirm' in result.fieldErrors }
  }

  const recordPayment = (amountCents: number, paidOn: string, note: string) =>
    mutate(() => sponsorsService.recordPayment(editionId, dealId(), { amount_cents: amountCents, paid_on: paidOn, note: blank(note) }))
  const deletePayment = (paymentId: string) => mutate(() => sponsorsService.deletePayment(editionId, dealId(), paymentId))

  const logContact = (channel: ContactChannelValue, happenedAt: string, summary: string) =>
    mutate(() => sponsorsService.logContact(editionId, sponsorId, { channel, happened_at: happenedAt ? new Date(happenedAt).toISOString() : null, summary: summary.trim() }))
  const deleteContact = (contactId: string) => mutate(() => sponsorsService.deleteContact(editionId, sponsorId, contactId))

  const upload = (kind: AttachmentKind, file: File) => mutate(() => sponsorsService.upload(editionId, sponsorId, kind, file))
  const deleteAttachment = (attachmentId: string) => mutate(() => sponsorsService.deleteAttachment(editionId, attachmentId))

  /** Pede uma URL assinada na hora do clique; ela expira em poucos minutos. */
  async function attachmentUrl(attachmentId: string): Promise<string | null> {
    try {
      return (await sponsorsService.attachmentUrl(editionId, attachmentId)).data.url
    } catch {
      return null
    }
  }

  const deliverableBody = (input: DeliverableInput) => ({ title: input.title.trim(), owner_id: input.ownerId || null, due_on: input.dueOn || null })
  const createDeliverable = (input: DeliverableInput) => mutate(() => sponsorsService.createDeliverable(editionId, dealId(), deliverableBody(input)))
  const setDeliverableStatus = (deliverableId: string, status: DeliverableStatusValue) => mutate(() => sponsorsService.updateDeliverable(editionId, deliverableId, { status }))
  const deleteDeliverable = (deliverableId: string) => mutate(() => sponsorsService.deleteDeliverable(editionId, deliverableId))

  return {
    sponsor, tiers, members, deal, selectedDealId, state, load,
    saveSponsor, saveDeal, openDeal, move, recordPayment, deletePayment, logContact, deleteContact,
    upload, deleteAttachment, attachmentUrl, createDeliverable, setDeliverableStatus, deleteDeliverable,
  }
}
