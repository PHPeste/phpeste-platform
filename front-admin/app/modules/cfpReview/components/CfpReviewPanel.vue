<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import AssignmentsCard from './AssignmentsCard.vue'
import AutoAssignModal from './AutoAssignModal.vue'
import ProposalView from './ProposalView.vue'
import ReviewKpis from './ReviewKpis.vue'
import ReviewQueue from './ReviewQueue.vue'
import ReviewsAggregate from './ReviewsAggregate.vue'
import ScoreForm from './ScoreForm.vue'
import ShortcutsHelp from './ShortcutsHelp.vue'
import { formatScore, useCfpReview } from '../composables/useCfpReview'
import locale from '../locales/pt-br'

const props = defineProps<{ editionId: string, organizer: boolean, userId: string | null, readOnly: boolean }>()

const review = useCfpReview(props.editionId, { organizer: props.organizer, userId: props.userId })
const {
  items, summary, reviewers, state, filter, search, filtered, counts, selectedId, selected, detailState, proposal, criteria,
  myReview, others, othersLocked, assignments, form, activeCriterion, busy, errorMessage, isAssignedToMe, canReview,
} = review
const toast = useToast()

const queue = ref<InstanceType<typeof ReviewQueue> | null>(null)
const scoreForm = ref<InstanceType<typeof ScoreForm> | null>(null)
const detail = ref<HTMLElement | null>(null)
const autoOpen = ref(false)
const exporting = ref(false)

async function focusDetail() {
  await nextTick()
  detail.value?.focus()
}

async function open(id: string) {
  await review.select(id)
  await focusDetail()
}

async function go(direction: 1 | -1) {
  await review.move(direction)
  await focusDetail()
}

async function saveDraft() {
  if (await review.saveDraft()) toast.success(locale.draftSaved)
}

async function submitNext() {
  const number = selected.value?.number
  if (await review.submitAndNext()) {
    toast.success(locale.submitted.replace('{n}', String(number)))
    await focusDetail()
  }
}

async function assign(reviewerId: string) {
  const error = await review.assign(reviewerId)
  const name = reviewers.value.find((reviewer) => reviewer.id === reviewerId)?.name ?? ''
  if (error) toast.error(error)
  else toast.success(locale.assigned.replace('{nome}', name).replace('{n}', String(selected.value?.number)))
}

async function unassign(reviewerId: string, name: string) {
  const error = await review.unassign(reviewerId)
  if (error) toast.error(error)
  else toast.success(locale.unassigned.replace('{nome}', name).replace('{n}', String(selected.value?.number)))
}

function onAutoDone(created: number, unfilled: number[]) {
  toast.success(created ? locale.autoDone.replace('{n}', String(created)) : locale.autoDoneNone)
  if (unfilled.length) toast.warning(locale.autoUnfilled.replace('{lista}', unfilled.map((n) => `#${n}`).join(', ')))
}

async function exportCsv() {
  exporting.value = true
  try {
    const file = await review.exportCsv()
    const url = URL.createObjectURL(file)
    const link = document.createElement('a')
    link.href = url
    link.download = 'notas-cfp.csv'
    link.click()
    URL.revokeObjectURL(url)
    toast.success(locale.exported)
  } catch {
    toast.error(locale.genericError)
  } finally {
    exporting.value = false
  }
}

function isTyping(target: EventTarget | null) {
  if (!(target instanceof HTMLElement)) return false
  return target.isContentEditable || ['TEXTAREA', 'SELECT'].includes(target.tagName) || (target.tagName === 'INPUT' && !['radio', 'checkbox'].includes((target as HTMLInputElement).type))
}

function onKeydown(event: KeyboardEvent) {
  if (event.ctrlKey || event.metaKey || event.altKey || event.defaultPrevented) return
  if (document.querySelector('dialog[open]')) return

  if (isTyping(event.target)) {
    if (event.key === 'Escape') (event.target as HTMLElement).blur()
    return
  }

  const target = event.target as HTMLElement | null
  const onControl = !!target?.closest('button, a, summary')
  const key = event.key.toLowerCase()
  const actions: Record<string, () => unknown> = {
    '/': () => queue.value?.focusSearch(),
    j: () => go(1),
    k: () => go(-1),
    s: () => canReview.value && !props.readOnly && saveDraft(),
    c: () => canReview.value && !props.readOnly && scoreForm.value?.focusComment(),
  }

  if (/^[1-5]$/.test(key) && !props.readOnly) {
    event.preventDefault()
    review.scoreActive(Number(key))
    return
  }
  if (key === 'enter' && !onControl && canReview.value && !props.readOnly) {
    event.preventDefault()
    submitNext()
    return
  }
  const action = actions[key]
  if (action) {
    event.preventDefault()
    action()
  }
}

onMounted(async () => {
  window.addEventListener('keydown', onKeydown)
  await review.load()
  if (selectedId.value) await focusDetail()
})
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <section class="flex flex-col gap-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl leading-tight font-semibold">{{ locale.title }}</h1>
        <p class="text-(--ph-ink-2)">{{ locale.subtitle }}</p>
      </div>
      <div v-if="organizer && state === 'ready'" class="flex flex-wrap gap-3">
        <PhButton v-if="!readOnly" variant="ghost" @click="autoOpen = true">{{ locale.autoAssign }}</PhButton>
        <PhButton variant="ghost" :loading="exporting" @click="exportCsv">{{ exporting ? locale.exporting : locale.export }}</PhButton>
      </div>
    </header>

    <p v-if="state === 'loading'" role="status" aria-live="polite" class="flex items-center gap-2 text-(--ph-ink-2)">
      <span class="loading loading-spinner loading-sm" aria-hidden="true" />{{ locale.loading }}
    </p>
    <div v-else-if="state === 'error'" role="alert" class="alert alert-error alert-soft">
      <span>{{ locale.loadError }}</span>
      <PhButton variant="ghost" @click="review.load">{{ locale.retry }}</PhButton>
    </div>

    <template v-else>
      <ReviewKpis v-if="organizer && summary" :summary="summary" />

      <div class="grid items-start gap-6 lg:grid-cols-[minmax(18rem,1fr)_2fr]">
        <div class="flex flex-col gap-4 lg:sticky lg:top-4">
          <ReviewQueue
            ref="queue"
            v-model:filter="filter"
            v-model:search="search"
            :items="filtered"
            :total="items.length"
            :counts="counts"
            :selected-id="selectedId"
            :organizer="organizer"
            @select="open"
          />
          <ShortcutsHelp />
        </div>

        <PhCard>
          <div ref="detail" tabindex="-1" class="flex flex-col gap-6 outline-none" aria-live="polite">
            <p v-if="!selectedId" class="text-(--ph-ink-2)">{{ items.length ? locale.chooseProposal : organizer ? locale.emptyOrg : locale.emptyQueue }}</p>
            <p v-else-if="detailState === 'loading' && !proposal" role="status" class="flex items-center gap-2 text-(--ph-ink-2)">
              <span class="loading loading-spinner loading-sm" aria-hidden="true" />{{ locale.loading }}
            </p>
            <div v-else-if="detailState === 'error'" role="alert" class="alert alert-error alert-soft">
              <span>{{ locale.loadError }}</span>
              <PhButton variant="ghost" @click="selectedId && review.select(selectedId)">{{ locale.retry }}</PhButton>
            </div>
            <template v-else-if="proposal">
              <ProposalView :proposal="proposal" />

              <ScoreForm
                v-if="canReview && !readOnly"
                ref="scoreForm"
                v-model:comment="form.comment"
                :criteria="criteria"
                :scores="form.scores"
                :active="activeCriterion"
                :busy="busy"
                :error="errorMessage"
                @score="review.setScore"
                @focus-criterion="activeCriterion = $event"
                @skip="go(1)"
                @draft="saveDraft"
                @submit="submitNext"
              />
              <p v-else-if="myReview?.status === 'submitted'" class="alert alert-success alert-soft text-sm">
                {{ locale.submittedReadOnly.replace('{nota}', formatScore(myReview.weightedScore)) }}
              </p>
              <p v-else-if="!organizer && !isAssignedToMe" class="text-sm text-(--ph-ink-2)">{{ locale.notAssigned }}</p>

              <ReviewsAggregate
                v-if="organizer || myReview?.status === 'submitted'"
                :criteria="criteria"
                :others="others"
                :locked="othersLocked"
                :final-score="selected?.score ?? null"
                :averages="organizer ? (selected?.criterionAverages ?? {}) : null"
              />

              <AssignmentsCard v-if="organizer && !readOnly" :assignments="assignments" :reviewers="reviewers" @assign="assign" @unassign="unassign" />
            </template>
          </div>
        </PhCard>
      </div>
    </template>

    <AutoAssignModal v-if="organizer" v-model="autoOpen" :run="review.autoAssign" @done="onAutoDone" />
  </section>
</template>
