<script setup lang="ts">
import locale from '../locales/pt-br'
import { formatScore } from '../composables/useCfpReview'
import type { EvaluationSummary } from '../types'

defineProps<{ summary: EvaluationSummary }>()
</script>

<template>
  <section :aria-label="locale.summary" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <PhKpi :label="locale.kpiProposals" :value="summary.proposals" :hint="locale.kpiReviewed.replace('{n}', String(summary.reviewed))" />
    <PhKpi :label="locale.kpiWithoutReviewer" :value="summary.withoutReviewer" :hint="locale.kpiWithoutReviewerHint" />
    <PhKpi :label="locale.kpiAverage" :value="formatScore(summary.averageScore)" :hint="locale.kpiAverageHint" />
    <PhKpi
      :label="locale.kpiNordeste"
      :value="summary.nordestePercent === null ? '—' : `${summary.nordestePercent}%`"
      :hint="locale.kpiNordesteHint.replace('{n}', String(summary.nordesteQuotaPercent))"
    />
  </section>
</template>
