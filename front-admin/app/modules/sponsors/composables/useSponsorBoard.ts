import { computed, ref } from 'vue'
import sponsorsService from '../services/sponsorsService'
import { blank, failure, translateDeal, translateTier } from './translate'
import type { Column, Deal, Indicators, Member, MoveResult, MutationResult, NewSponsorInput, Stage, Tier, TierInput } from '../types'

/** As quatro colunas do funil; perdidos ficam numa lista à parte. */
export const BOARD_STAGES: Stage[] = ['prospecting', 'negotiating', 'closed', 'paid']

export function useSponsorBoard(editionId: string) {
  const columns = ref<Column[]>([])
  const lost = ref<Deal[]>([])
  const indicators = ref<Indicators | null>(null)
  const tiers = ref<Tier[]>([])
  const members = ref<Member[]>([])
  const state = ref<'loading' | 'ready' | 'error'>('loading')

  const openCompanies = computed(() => columns.value.filter((column) => column.stage === 'prospecting' || column.stage === 'negotiating').reduce((sum, column) => sum + column.cards.length, 0))
  const goalShare = computed(() => (indicators.value?.goalCents ? indicators.value.committedCents / indicators.value.goalCents : null))
  const empty = computed(() => columns.value.every((column) => column.cards.length === 0) && lost.value.length === 0)

  async function refresh() {
    const [board, tierList] = await Promise.all([sponsorsService.board(editionId), sponsorsService.tiers(editionId)])
    const all = board.data.columns.map((column) => ({
      stage: column.stage,
      label: column.label,
      valueCents: column.value.amount,
      cards: column.cards.map((card) => translateDeal(card)),
    }))
    columns.value = all.filter((column) => BOARD_STAGES.includes(column.stage))
    lost.value = all.find((column) => column.stage === 'lost')?.cards ?? []
    indicators.value = {
      goalCents: board.data.indicators.goal?.amount ?? null,
      committedCents: board.data.indicators.committed.amount,
      receivedCents: board.data.indicators.received.amount,
      pipelineCents: board.data.indicators.pipeline.amount,
      deliverablesOverdue: board.data.indicators.deliverables_overdue,
    }
    tiers.value = tierList.data.map(translateTier)
  }

  async function load() {
    state.value = 'loading'
    try {
      await refresh()
      state.value = 'ready'
    } catch {
      state.value = 'error'
    }
    sponsorsService.team(editionId)
      .then((team) => { members.value = team.data.members.map((member) => ({ id: member.user_id, name: member.name ?? member.email ?? '' })) })
      .catch(() => { members.value = [] })
  }

  function find(dealId: string): Deal | undefined {
    return [...columns.value.flatMap((column) => column.cards), ...lost.value].find((deal) => deal.id === dealId)
  }

  /**
   * Arrastar o card: mostra na coluna nova na hora e chama a API. Se a API
   * recusa, o funil volta a ser o de antes. Cota sem vaga volta com
   * `needsConfirm` para a tela perguntar e chamar de novo com `confirm`.
   */
  async function move(dealId: string, to: Stage, options: { lostReason?: string, confirm?: boolean } = {}): Promise<MoveResult> {
    const deal = find(dealId)
    if (!deal) return { ok: false, message: '', fieldErrors: {}, needsConfirm: false }
    if (deal.stage === to) return { ok: true }

    const before = { columns: columns.value.map((column) => ({ ...column, cards: [...column.cards] })), lost: [...lost.value] }
    const target = columns.value.find((column) => column.stage === to)
    const position = target ? target.cards.length : 0
    columns.value = columns.value.map((column) => ({
      ...column,
      cards: column.stage === to ? [...column.cards.filter((card) => card.id !== dealId), { ...deal, stage: to }] : column.cards.filter((card) => card.id !== dealId),
    }))
    lost.value = to === 'lost' ? [...lost.value.filter((card) => card.id !== dealId), { ...deal, stage: to }] : lost.value.filter((card) => card.id !== dealId)

    try {
      await sponsorsService.move(editionId, dealId, {
        stage: to,
        position,
        ...(to === 'lost' ? { lost_reason: options.lostReason?.trim() ?? '' } : {}),
        ...(options.confirm ? { confirm: true } : {}),
      })
    } catch (error) {
      columns.value = before.columns
      lost.value = before.lost
      const result = failure(error)
      return { ...result, needsConfirm: 'confirm' in result.fieldErrors }
    }

    try {
      await refresh()
    } catch {
      // O card já está no lugar certo; os totais voltam na próxima carga.
    }
    return { ok: true }
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

  const setGoal = (goalCents: number | null) => mutate(() => sponsorsService.setGoal(editionId, goalCents))

  const tierBody = (input: TierInput) => ({ name: input.name.trim(), price_cents: input.priceCents, slots: input.slots, benefits: input.benefits.map((item) => item.trim()).filter(Boolean) })
  const saveTier = (tierId: string | null, input: TierInput) => mutate(() => (tierId ? sponsorsService.updateTier(editionId, tierId, tierBody(input)) : sponsorsService.createTier(editionId, tierBody(input))))
  const deleteTier = (tierId: string) => mutate(() => sponsorsService.deleteTier(editionId, tierId))

  /** "Nova empresa": cadastra a empresa e abre o acordo na prospecção. */
  async function createSponsor(input: NewSponsorInput): Promise<MutationResult & { sponsorId?: string }> {
    let sponsorId: string
    try {
      const created = await sponsorsService.createSponsor(editionId, { name: input.name.trim(), contact_name: blank(input.contactName), contact_email: blank(input.contactEmail) })
      sponsorId = created.data.id
    } catch (error) {
      return failure(error)
    }
    const result = await mutate(() => sponsorsService.createSponsorship(editionId, sponsorId, {
      sponsor_tier_id: input.tierId || null,
      ...(input.valueCents === null ? {} : { value_cents: input.valueCents }),
      owner_id: input.ownerId || null,
    }))
    return result.ok ? { ok: true, sponsorId } : { ...result, sponsorId }
  }

  return { columns, lost, indicators, tiers, members, state, openCompanies, goalShare, empty, load, move, setGoal, saveTier, deleteTier, createSponsor }
}
