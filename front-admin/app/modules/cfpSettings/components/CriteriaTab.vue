<script setup lang="ts">
import { computed, ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import CriterionModal from './CriterionModal.vue'
import locale from '../locales/pt-br'
import type { Criterion, CriterionInput, MutationResult } from '../types'

const props = defineProps<{
  criteria: Criterion[]
  totalWeight: number
  submittedReviews: number
  disabled: boolean
  save: (criterion: Criterion | null, input: CriterionInput, confirmed?: boolean) => Promise<MutationResult>
  remove: (criterion: Criterion, confirmed?: boolean) => Promise<MutationResult>
}>()

const toast = useToast()
const editing = ref<Criterion | null>(null)
const open = ref(false)

const share = (weight: number) => (props.totalWeight ? Math.round((weight / props.totalWeight) * 100) : 0)
const reviewsText = computed(() => (props.submittedReviews === 1 ? locale.reviewsExistOne : locale.reviewsExist.replace('{n}', String(props.submittedReviews))))

function edit(criterion: Criterion | null) {
  editing.value = criterion
  open.value = true
}
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">{{ locale.criteriaTitle }}</h2>
        <PhButton v-if="!disabled && criteria.length" @click="edit(null)">{{ locale.newCriterion }}</PhButton>
      </div>
      <p v-if="submittedReviews > 0" class="alert alert-warning alert-soft text-sm">{{ reviewsText }} {{ locale.impactText }}</p>
      <PhEmptyState v-if="!criteria.length" :title="locale.noCriteria" :description="locale.noCriteriaHint">
        <PhButton v-if="!disabled" @click="edit(null)">{{ locale.newCriterion }}</PhButton>
      </PhEmptyState>
      <ul v-else class="flex flex-col">
        <li v-for="criterion in criteria" :key="criterion.id" class="flex items-center justify-between gap-4 border-t border-(--ph-line) py-3.5 first:border-t-0">
          <div class="flex min-w-0 flex-col gap-0.5">
            <b class="font-medium">{{ criterion.name }}</b>
            <span v-if="criterion.description" class="text-sm text-(--ph-ink-2)">{{ criterion.description }}</span>
          </div>
          <div class="flex shrink-0 items-center gap-3">
            <span class="text-right text-sm">
              <PhBadge :dot="false">{{ locale.weightValue.replace('{n}', String(criterion.weight)) }}</PhBadge>
              <span class="ml-2 text-xs text-(--ph-ink-3) tabular-nums">{{ locale.weightShare.replace('{n}', String(share(criterion.weight))) }}</span>
            </span>
            <PhButton v-if="!disabled" variant="ghost" :aria-label="`${locale.edit} ${criterion.name}`" @click="edit(criterion)">{{ locale.edit }}</PhButton>
          </div>
        </li>
      </ul>
      <p class="text-xs text-(--ph-ink-3)">{{ locale.criteriaFooter }}</p>
    </div>
    <CriterionModal
      v-model="open"
      :criterion="editing"
      :submitted-reviews="submittedReviews"
      :save="save"
      :remove="remove"
      @saved="toast.success(locale.criterionSaved.replace('{nome}', $event))"
      @removed="toast.success(locale.criterionRemoved.replace('{nome}', $event))"
    />
  </PhCard>
</template>
