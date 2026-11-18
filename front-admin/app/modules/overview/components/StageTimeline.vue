<script setup lang="ts">
import { computed } from 'vue'
import { fillText } from '../composables/useOverview'
import locale from '../locales/pt-br'
import type { StageStep } from '../types'

const props = defineProps<{ steps: StageStep[], daysUntilEvent: number | null }>()

const countdown = computed(() => {
  if (props.daysUntilEvent === null) return locale.noDate
  if (props.daysUntilEvent === 0) return locale.today
  return props.daysUntilEvent === 1 ? locale.oneDay : fillText(locale.daysUntil, { n: props.daysUntilEvent })
})

const note = (step: StageStep) => ({ done: locale.stateDone, current: locale.stateCurrent, next: locale.stateNext, upcoming: step.note })[step.state]
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-5">
      <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="text-lg font-semibold">{{ locale.stageTitle }}</h2>
        <p class="text-sm text-(--ph-ink-2)">{{ countdown }}</p>
      </div>
      <ol class="grid grid-cols-1 gap-2 sm:grid-cols-5">
        <li
          v-for="step in steps"
          :key="step.status"
          :aria-current="step.state === 'current' ? 'step' : undefined"
          class="flex flex-col gap-1 rounded-(--ph-radius) border px-4 py-3"
          :class="step.state === 'current'
            ? 'border-(--ph-brand) bg-(--ph-brand) text-(--ph-on-brand)'
            : step.state === 'done' ? 'border-(--ph-line) bg-(--ph-surface-2)' : 'border-(--ph-line)'"
        >
          <span class="text-sm font-semibold">{{ step.label }}</span>
          <span class="text-xs" :class="step.state === 'current' ? '' : 'text-(--ph-ink-3)'">{{ note(step) }}</span>
        </li>
      </ol>
    </div>
  </PhCard>
</template>
