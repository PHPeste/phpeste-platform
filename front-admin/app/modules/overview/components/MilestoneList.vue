<script setup lang="ts">
import { formatShortDate } from '../composables/useOverview'
import locale from '../locales/pt-br'
import type { Milestone } from '../types'

defineProps<{ milestones: Milestone[] }>()
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-2">
      <h2 class="text-lg font-semibold">{{ locale.milestonesTitle }}</h2>
      <p v-if="milestones.length === 0" class="py-4 text-sm text-(--ph-ink-2)">{{ locale.milestonesEmpty }}</p>
      <ol v-else class="flex flex-col">
        <li v-for="milestone in milestones" :key="milestone.key" class="flex items-center justify-between gap-3 border-t border-(--ph-line) py-3 first:border-t-0">
          <div class="flex flex-col">
            <span class="font-medium">{{ milestone.title }}</span>
            <span class="text-xs text-(--ph-ink-3)">{{ milestone.description }}</span>
          </div>
          <time :datetime="milestone.date" class="shrink-0 text-sm tabular-nums">{{ formatShortDate(milestone.date) }}</time>
        </li>
      </ol>
    </div>
  </PhCard>
</template>
