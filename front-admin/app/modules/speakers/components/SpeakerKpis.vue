<script setup lang="ts">
import locale from '../locales/pt-br'
import type { SpeakersSummary } from '../types'

defineProps<{ summary: SpeakersSummary }>()
</script>

<template>
  <section :aria-label="locale.summary" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <PhKpi :label="locale.kpiConfirmed" :value="summary.confirmed" :hint="locale.kpiConfirmedHint.replace('{n}', String(summary.slots))" />
    <PhKpi :label="locale.kpiAwaiting" :value="summary.awaiting" :hint="locale.kpiAwaitingHint" />
    <PhKpi :label="locale.kpiWaitlist" :value="summary.waitlisted" />
    <PhKpi
      :label="locale.kpiNordeste"
      :value="summary.nordestePercent === null ? '—' : `${summary.nordestePercent}%`"
      :hint="locale.kpiNordesteHint.replace('{n}', String(summary.nordesteQuotaPercent))"
    />
  </section>
</template>
