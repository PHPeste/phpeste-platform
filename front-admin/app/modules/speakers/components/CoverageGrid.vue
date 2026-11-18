<script setup lang="ts">
import locale from '../locales/pt-br'
import type { CoverageCell } from '../types'

defineProps<{ coverage: CoverageCell[], missing: string[] }>()
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-4">
      <div class="flex flex-col gap-1">
        <h2 class="text-lg font-semibold">{{ locale.coverageTitle }}</h2>
        <p class="text-sm text-(--ph-ink-2)">{{ locale.coverageHint }}</p>
      </div>
      <ul class="grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-9">
        <li
          v-for="cell in coverage"
          :key="cell.state"
          class="flex flex-col items-center gap-0.5 rounded-(--ph-radius) border px-2 py-3 text-center"
          :class="cell.confirmed ? 'border-(--ph-line) bg-(--ph-surface)' : 'border-(--ph-danger) bg-(--ph-danger-bg)'"
          :data-state="cell.state"
        >
          <b class="text-sm font-semibold">{{ cell.state }}</b>
          <span class="text-2xl leading-none font-semibold tabular-nums" :class="cell.confirmed ? '' : 'text-(--ph-danger)'">{{ cell.confirmed }}</span>
          <span class="text-xs" :class="cell.confirmed ? 'text-(--ph-ink-3)' : 'text-(--ph-danger)'">
            {{ cell.confirmed === 0 ? locale.coverageMissing : cell.confirmed === 1 ? locale.coverageConfirmedOne : locale.coverageConfirmed }}
          </span>
        </li>
      </ul>
      <p class="text-sm" :class="missing.length ? 'font-medium text-(--ph-danger)' : 'text-(--ph-ok)'">
        {{ missing.length ? (missing.length === 1 ? locale.coverageMissingOne : locale.coverageMissingCount.replace('{n}', String(missing.length))).replace('{lista}', missing.join(', ')) : locale.coverageComplete }}
      </p>
    </div>
  </PhCard>
</template>
