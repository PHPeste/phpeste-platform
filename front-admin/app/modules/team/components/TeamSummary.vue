<script setup lang="ts">
import { computed } from 'vue'
import locale from '../locales/pt-br'
import type { TeamSummary } from '../types'

const props = defineProps<{ summary: TeamSummary }>()

const pendingHint = computed(() => {
  const n = props.summary.pendingInvitations
  if (n === 0) return locale.noPending
  return n === 1 ? locale.pendingOne : locale.pendingMany.replace('{n}', String(n))
})
</script>

<template>
  <section :aria-label="locale.summary" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <PhKpi :label="locale.people" :value="summary.people" :hint="pendingHint" />
    <PhKpi :label="locale.organizers" :value="summary.byRole.organizador" />
    <PhKpi :label="locale.reviewers" :value="summary.byRole.avaliador" />
    <PhKpi :label="locale.volunteers" :value="summary.byRole.voluntario" />
  </section>
</template>
