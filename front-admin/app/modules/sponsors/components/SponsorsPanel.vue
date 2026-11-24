<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import SponsorBoard from './SponsorBoard.vue'
import GoalModal from './GoalModal.vue'
import TierModal from './TierModal.vue'
import NewSponsorModal from './NewSponsorModal.vue'
import LostReasonModal from './LostReasonModal.vue'
import { useSponsorBoard } from '../composables/useSponsorBoard'
import { formatCents } from '../composables/format'
import locale from '../locales/pt-br'
import type { Deal, Stage, Tier } from '../types'

const props = defineProps<{ editionId: string, readOnly: boolean }>()

const board = useSponsorBoard(props.editionId)
const { columns, lost, indicators, tiers, members, state, openCompanies, goalShare, empty } = board
const toast = useToast()

const goalOpen = ref(false)
const tierOpen = ref(false)
const editingTier = ref<Tier | null>(null)
const removingTier = ref<Tier | null>(null)
const removeTierOpen = ref(false)
const newOpen = ref(false)
const pending = ref<{ deal: Deal, stage: Stage, lostReason?: string } | null>(null)
const lostOpen = ref(false)
const confirmOpen = ref(false)
const confirmText = ref('')
const busy = ref(false)

const percent = new Intl.NumberFormat('pt-BR', { style: 'percent', maximumFractionDigits: 0 })
const committedHint = computed(() => (goalShare.value === null ? undefined : locale.kpiGoalShare.replace('{p}', percent.format(goalShare.value))))
const receivedHint = computed(() => {
  const outstanding = (indicators.value?.committedCents ?? 0) - (indicators.value?.receivedCents ?? 0)
  return outstanding > 0 ? locale.kpiOutstanding.replace('{valor}', formatCents(outstanding)) : undefined
})
const pipelineHint = computed(() => (openCompanies.value === 1 ? locale.kpiCompany : locale.kpiCompanies.replace('{n}', String(openCompanies.value))))
const overdueText = computed(() => {
  const count = indicators.value?.deliverablesOverdue ?? 0
  if (count === 0) return null
  return count === 1 ? locale.overdueOne : locale.overdue.replace('{n}', String(count))
})

function requestMove(deal: Deal, stage: Stage) {
  pending.value = { deal, stage }
  if (stage === 'lost') {
    lostOpen.value = true
    return
  }
  runMove(false)
}

async function runMove(confirm: boolean, lostReason?: string) {
  const move = pending.value
  if (!move) return
  if (lostReason !== undefined) move.lostReason = lostReason
  busy.value = true
  const result = await board.move(move.deal.id, move.stage, { lostReason: move.lostReason, confirm })
  busy.value = false
  if (result.ok) {
    lostOpen.value = false
    confirmOpen.value = false
    pending.value = null
    toast.success(locale.moved.replace('{empresa}', move.deal.sponsorName).replace('{etapa}', locale.stages[move.stage]))
    return
  }
  if (result.needsConfirm && !confirm) {
    lostOpen.value = false
    confirmText.value = result.message
    confirmOpen.value = true
    return
  }
  confirmOpen.value = false
  toast.error(result.message)
}

function editTier(tier: Tier | null) {
  editingTier.value = tier
  tierOpen.value = true
}

function askRemoveTier(tier: Tier) {
  removingTier.value = tier
  removeTierOpen.value = true
}

async function removeTier() {
  if (!removingTier.value) return
  busy.value = true
  const result = await board.deleteTier(removingTier.value.id)
  busy.value = false
  removeTierOpen.value = false
  if (result.ok) toast.success(locale.tierRemoved)
  else toast.error(result.message)
}

function created(name: string) {
  toast.success(locale.created.replace('{empresa}', name))
}

onMounted(() => board.load())
</script>

<template>
  <section class="flex flex-col gap-6">
    <header class="flex flex-wrap items-end justify-between gap-3">
      <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl leading-tight font-semibold">{{ locale.title }}</h1>
        <p class="text-(--ph-ink-2)">{{ locale.subtitle }}</p>
      </div>
    </header>

    <p v-if="readOnly" role="status" class="alert alert-info alert-soft text-sm">{{ locale.readOnly }}</p>

    <PhPageSkeleton v-if="state === 'loading'" :cards="4" :lines="4" />
    <PhLoadError v-else-if="state === 'error'" :title="locale.loadError" @retry="board.load" />

    <template v-else-if="indicators">
      <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4" data-testid="sponsors-indicators">
        <div class="relative">
          <PhKpi :label="locale.kpiGoal" :value="indicators.goalCents === null ? locale.kpiNoGoal : formatCents(indicators.goalCents)" />
          <button v-if="!readOnly" type="button" class="btn btn-ghost btn-xs absolute top-3 right-3" @click="goalOpen = true">{{ locale.editGoal }}</button>
        </div>
        <PhKpi :label="locale.kpiCommitted" :value="formatCents(indicators.committedCents)" :hint="committedHint" />
        <PhKpi :label="locale.kpiReceived" :value="formatCents(indicators.receivedCents)" :hint="receivedHint" />
        <PhKpi :label="locale.kpiPipeline" :value="formatCents(indicators.pipelineCents)" :hint="pipelineHint" />
      </div>
      <p v-if="overdueText" role="status" class="alert alert-warning alert-soft text-sm" data-testid="sponsors-overdue">{{ overdueText }}</p>

      <section class="card border border-base-300 bg-base-100">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-(--ph-line) px-5 py-4">
          <div>
            <h2 class="text-lg font-semibold">{{ locale.boardTitle }}</h2>
            <p v-if="!readOnly && !empty" class="text-xs text-(--ph-ink-3)">{{ locale.boardHint }}</p>
          </div>
          <PhButton v-if="!readOnly" @click="newOpen = true">{{ locale.newCompany }}</PhButton>
        </div>
        <div class="p-5">
          <PhEmptyState v-if="empty" :title="locale.emptyBoard" :description="locale.emptyBoardHint">
            <PhButton v-if="!readOnly" @click="newOpen = true">{{ locale.newCompany }}</PhButton>
          </PhEmptyState>
          <template v-else>
            <SponsorBoard :columns="columns" :disabled="readOnly || busy" @move="requestMove" />
            <details v-if="lost.length" class="mt-5" data-testid="sponsors-lost">
              <summary class="cursor-pointer text-sm font-medium text-(--ph-ink-2)">{{ locale.lostTitle.replace('{n}', String(lost.length)) }}</summary>
              <ul class="mt-3 flex flex-col divide-y divide-(--ph-line) rounded-(--radius-box) border border-(--ph-line)">
                <li v-for="deal in lost" :key="deal.id" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                  <div>
                    <NuxtLink :to="`/patrocinadores/${deal.sponsorId}`" class="font-medium">{{ deal.sponsorName }}</NuxtLink>
                    <p v-if="deal.lostReason" class="text-xs text-(--ph-ink-3)">{{ locale.lostReasonShort.replace('{motivo}', deal.lostReason) }}</p>
                  </div>
                  <span class="text-sm tabular-nums">{{ formatCents(deal.valueCents) }}</span>
                </li>
              </ul>
            </details>
          </template>
        </div>
      </section>

      <section class="card overflow-hidden border border-base-300 bg-base-100">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-(--ph-line) px-5 py-4">
          <h2 class="text-lg font-semibold">{{ locale.tiersTitle }}</h2>
          <PhButton v-if="!readOnly" variant="secondary" @click="editTier(null)">{{ locale.newTier }}</PhButton>
        </div>
        <div v-if="!tiers.length" class="p-5">
          <PhEmptyState :title="locale.noTiers" :description="locale.noTiersHint">
            <PhButton v-if="!readOnly" variant="secondary" @click="editTier(null)">{{ locale.newTier }}</PhButton>
          </PhEmptyState>
        </div>
        <PhTable v-else :caption="locale.tiersTitle">
          <thead>
            <tr>
              <th scope="col">{{ locale.colTier }}</th>
              <th scope="col" class="text-right">{{ locale.colPrice }}</th>
              <th scope="col" class="text-right">{{ locale.colSlots }}</th>
              <th scope="col" class="text-right">{{ locale.colTaken }}</th>
              <th scope="col">{{ locale.colBenefits }}</th>
              <th v-if="!readOnly" scope="col"><span class="sr-only">{{ locale.editTiers }}</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="tier in tiers" :key="tier.id">
              <td class="font-medium">{{ tier.name }}</td>
              <td class="text-right tabular-nums">{{ formatCents(tier.priceCents) }}</td>
              <td class="text-right tabular-nums">{{ tier.slots ?? locale.unlimited }}</td>
              <td class="text-right tabular-nums">{{ tier.slotsTaken }}</td>
              <td class="text-sm text-(--ph-ink-2)">{{ tier.benefits.join(', ') || '—' }}</td>
              <td v-if="!readOnly" class="text-right whitespace-nowrap">
                <button type="button" class="btn btn-ghost btn-sm" :aria-label="locale.editTier.replace('{nome}', tier.name)" @click="editTier(tier)">{{ locale.edit }}</button>
                <button type="button" class="btn btn-ghost btn-sm text-error" :aria-label="locale.removeTier.replace('{nome}', tier.name)" @click="askRemoveTier(tier)">{{ locale.remove }}</button>
              </td>
            </tr>
          </tbody>
        </PhTable>
      </section>
    </template>

    <GoalModal v-model="goalOpen" :goal-cents="indicators?.goalCents ?? null" :save="board.setGoal" @saved="toast.success(locale.goalSaved)" />
    <TierModal v-model="tierOpen" :tier="editingTier" :save="board.saveTier" @saved="toast.success(locale.tierSaved)" />
    <NewSponsorModal v-model="newOpen" :tiers="tiers" :members="members" :create="board.createSponsor" @created="created" />
    <LostReasonModal v-model="lostOpen" :company="pending?.deal.sponsorName ?? ''" :busy="busy" @confirm="runMove(false, $event)" />

    <PhModal v-model="confirmOpen" :title="locale.confirmTitle">
      <p>{{ confirmText }}</p>
      <template #footer>
        <PhButton variant="ghost" @click="confirmOpen = false">{{ locale.back }}</PhButton>
        <PhButton :loading="busy" @click="runMove(true)">{{ locale.confirmText }}</PhButton>
      </template>
    </PhModal>

    <PhModal v-model="removeTierOpen" :title="locale.removeTier.replace('{nome}', removingTier?.name ?? '')">
      <template #footer>
        <PhButton variant="ghost" @click="removeTierOpen = false">{{ locale.back }}</PhButton>
        <PhButton variant="danger" :loading="busy" @click="removeTier">{{ locale.remove }}</PhButton>
      </template>
    </PhModal>
  </section>
</template>
