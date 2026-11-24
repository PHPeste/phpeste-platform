<script setup lang="ts">
import { computed } from 'vue'
import { formatCents } from '../composables/format'
import locale from '../locales/pt-br'
import type { BudgetLine, Summary } from '../types'

const props = defineProps<{ summary: Summary, disabled: boolean }>()
defineEmits<{ edit: [line: BudgetLine | null] }>()

const lines = computed(() => props.summary.budget)
const width = (line: BudgetLine) => (line.budgetCents ? Math.min(100, Math.round((line.committedCents / line.budgetCents) * 100)) : 0)
const text = (line: BudgetLine) => (line.budgetCents === null
  ? locale.budgetLineNoBudget.replace('{usado}', formatCents(line.committedCents))
  : locale.budgetLine.replace('{usado}', formatCents(line.committedCents)).replace('{orcado}', formatCents(line.budgetCents)))
const detail = (line: BudgetLine) => {
  if (line.budgetCents === null || line.remainingCents === null) return ''
  return line.overBudget ? locale.budgetOver.replace('{valor}', formatCents(-line.remainingCents)) : locale.budgetRemaining.replace('{valor}', formatCents(line.remainingCents))
}
</script>

<template>
  <section class="card border border-base-300 bg-base-100 p-5" aria-labelledby="budget-title" data-testid="finance-budget">
    <div class="mb-1 flex flex-wrap items-center justify-between gap-3">
      <h2 id="budget-title" class="text-lg font-semibold">{{ locale.budgetTitle }}</h2>
      <PhButton v-if="!disabled" variant="secondary" @click="$emit('edit', null)">{{ locale.newCategory }}</PhButton>
    </div>
    <p class="mb-4 text-xs text-(--ph-ink-3)">{{ locale.budgetHint }}</p>

    <PhEmptyState v-if="!lines.length" :title="locale.noBudget" :description="locale.noBudgetHint" />
    <ul v-else class="flex flex-col gap-4">
      <li v-for="line in lines" :key="line.id" class="flex flex-col gap-1.5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
          <span class="font-medium">{{ line.name }}</span>
          <span class="flex items-center gap-2 text-sm tabular-nums">
            {{ text(line) }}
            <button v-if="!disabled" type="button" class="btn btn-ghost btn-xs" :aria-label="locale.editCategory.replace('{nome}', line.name)" @click="$emit('edit', line)">{{ locale.edit }}</button>
          </span>
        </div>
        <div
          v-if="line.budgetCents !== null"
          class="h-2.5 overflow-hidden rounded-full bg-(--ph-surface-2)"
          role="meter"
          :aria-valuenow="line.committedCents / 100"
          aria-valuemin="0"
          :aria-valuemax="line.budgetCents / 100"
          :aria-valuetext="text(line)"
          :aria-label="line.name"
        >
          <div class="h-full rounded-full" :class="line.overBudget ? 'bg-(--ph-danger)' : 'bg-(--ph-brand)'" :style="{ width: `${width(line)}%` }" />
        </div>
        <p v-if="detail(line)" class="text-xs" :class="line.overBudget ? 'font-medium text-(--ph-danger)' : 'text-(--ph-ink-3)'">
          {{ detail(line) }}<template v-if="line.percentUsed !== null"> · {{ locale.budgetPercent.replace('{p}', String(line.percentUsed)) }}</template>
        </p>
      </li>
    </ul>
    <p v-if="lines.length" class="mt-4 border-t border-(--ph-line) pt-3 text-sm font-medium tabular-nums">
      {{ locale.budgetTotal.replace('{usado}', formatCents(summary.committedCents)).replace('{orcado}', formatCents(summary.budgetedCents)) }}
    </p>
  </section>
</template>
