<script setup lang="ts">
import { computed } from 'vue'
import type { BadgeStatus } from '@/application/shared/components/ui/Badge.vue'
import locale from '../locales/pt-br'
import type { CfpGeneral, CfpSettings } from '../types'

const props = defineProps<{ settings: CfpSettings, errors: Record<string, string>, disabled: boolean }>()
const general = defineModel<CfpGeneral>({ required: true })

const TONE: Record<string, BadgeStatus> = { open: 'ok', scheduled: 'info', closed: 'warn', draft: 'neutral' }
const daysLeft = computed(() => {
  const n = props.settings.daysUntilClose
  if (n === null) return null
  return n === 1 ? locale.daysLeftOne : locale.daysLeft.replace('{n}', String(n))
})
</script>

<template>
  <div class="grid items-start gap-6 lg:grid-cols-2">
    <PhCard>
      <div class="flex flex-col gap-4">
        <h2 class="text-lg font-semibold">{{ locale.windowTitle }}</h2>
        <PhInput v-model="general.opensOn" type="date" :label="locale.opensOn" :error="errors.opensOn" :disabled="disabled" />
        <PhInput v-model="general.closesOn" type="date" :label="locale.closesOn" :error="errors.closesOn" :disabled="disabled" />
        <PhInput v-model="general.resultsOn" type="date" :label="locale.resultsOn" :error="errors.resultsOn" :disabled="disabled" />
        <p class="text-xs text-(--ph-ink-3)">{{ locale.windowHint }}</p>
        <div class="flex flex-wrap items-center gap-3">
          <PhBadge :status="TONE[settings.status] ?? 'neutral'">{{ settings.statusLabel }}</PhBadge>
          <span v-if="daysLeft" class="text-sm text-(--ph-ink-2)">{{ daysLeft }}</span>
        </div>
      </div>
    </PhCard>

    <PhCard>
      <div class="flex flex-col gap-4">
        <h2 class="text-lg font-semibold">{{ locale.rulesTitle }}</h2>
        <PhCheckbox v-model="general.anonymousReview" :label="locale.anonymous" :disabled="disabled" />
        <PhCheckbox v-model="general.requireNordesteQuota" :label="locale.requireQuota" :disabled="disabled" />
        <PhInput
          v-model.number="general.nordesteQuotaPercent"
          type="number"
          :label="locale.quota"
          :hint="locale.quotaHint"
          :error="errors.nordesteQuotaPercent"
          :disabled="disabled || !general.requireNordesteQuota"
        />
        <PhInput v-model.number="general.proposalsPerPerson" type="number" :label="locale.perPerson" :hint="locale.perPersonHint" :error="errors.proposalsPerPerson" :disabled="disabled" />
      </div>
    </PhCard>
  </div>
</template>
