<script setup lang="ts">
import locale from '../locales/pt-br'
import { formatScore } from '../composables/useCfpReview'
import type { Criterion, OtherReview } from '../types'

defineProps<{ criteria: Criterion[], others: OtherReview[], locked: boolean, finalScore: number | null, averages: Record<string, number> | null }>()
</script>

<template>
  <section class="flex flex-col gap-3">
    <h3 class="text-lg font-semibold">{{ locale.othersTitle }}</h3>
    <p v-if="locked" class="text-sm text-(--ph-ink-2)">{{ locale.othersLocked }}</p>
    <template v-else>
      <dl v-if="averages" class="grid gap-2 rounded-(--ph-radius) border border-(--ph-line) p-3.5 sm:grid-cols-2">
        <div class="flex justify-between gap-3 sm:col-span-2">
          <dt class="font-medium">{{ locale.finalScore }}</dt>
          <dd class="text-lg font-semibold tabular-nums">{{ formatScore(finalScore) }}</dd>
        </div>
        <div v-for="criterion in criteria" :key="criterion.id" class="flex justify-between gap-3 text-sm">
          <dt class="text-(--ph-ink-2)">{{ criterion.name }}</dt>
          <dd class="tabular-nums">{{ formatScore(averages[criterion.id] ?? null) }}</dd>
        </div>
      </dl>
      <p v-if="!others.length" class="text-sm text-(--ph-ink-2)">{{ locale.noOthers }}</p>
      <ul v-else class="flex flex-col">
        <li v-for="(review, index) in others" :key="index" class="flex flex-col gap-1 border-t border-(--ph-line) py-2.5 first:border-t-0">
          <span class="flex justify-between gap-3 text-sm">
            <b class="font-medium">{{ review.reviewer }}</b>
            <span class="tabular-nums">{{ formatScore(review.weightedScore) }}</span>
          </span>
          <span class="text-xs text-(--ph-ink-3)">{{ criteria.map((criterion) => `${criterion.name}: ${review.scores[criterion.id] ?? '—'}`).join(' · ') }}</span>
          <p v-if="review.comment" class="text-sm text-(--ph-ink-2)">{{ review.comment }}</p>
        </li>
      </ul>
    </template>
  </section>
</template>
