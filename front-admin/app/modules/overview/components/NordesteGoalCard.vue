<script setup lang="ts">
import { computed } from 'vue'
import { fillText } from '../composables/useOverview'
import locale from '../locales/pt-br'
import type { NordesteGoal } from '../types'

const props = defineProps<{ goal: NordesteGoal }>()

const met = computed(() => (props.goal.percent ?? 0) >= props.goal.quota)
const headline = computed(() => fillText(props.goal.base === 'confirmed' ? locale.nordesteConfirmed : locale.nordesteAccepted, { percent: props.goal.percent ?? 0 }))
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-3">
      <h2 class="text-lg font-semibold">{{ locale.nordesteTitle }}</h2>
      <p class="text-sm text-(--ph-ink-2)">{{ fillText(locale.nordesteRule, { quota: goal.quota }) }}</p>

      <p v-if="goal.people === 0" class="text-sm text-(--ph-ink-2)">{{ locale.nordesteNone }}</p>
      <template v-else>
        <p class="flex flex-wrap items-center gap-2 font-semibold">
          {{ headline }}
          <PhBadge v-if="goal.requireQuota" :status="met ? 'ok' : 'warn'">{{ met ? locale.nordesteMet : locale.nordesteBelow }}</PhBadge>
        </p>
        <progress class="progress progress-primary w-full" :value="goal.percent ?? 0" max="100" :aria-label="headline" />
      </template>

      <div v-if="goal.missingStates.length" class="flex flex-wrap items-center gap-2 text-sm">
        <span class="text-(--ph-ink-2)">{{ locale.nordesteMissing }}</span>
        <PhBadge v-for="state in goal.missingStates" :key="state" status="neutral" :dot="false">{{ state }}</PhBadge>
      </div>
      <p v-else-if="goal.people > 0" class="text-sm text-(--ph-ink-2)">{{ locale.nordesteComplete }}</p>

      <NuxtLink to="/palestrantes?aba=cobertura" class="text-sm text-(--ph-link) hover:text-(--ph-brand)">{{ locale.nordesteCoverage }}</NuxtLink>
    </div>
  </PhCard>
</template>
