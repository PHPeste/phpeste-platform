<script setup lang="ts">
import { watch } from 'vue'
import { useEdition } from '@/application/shared/composables/useEdition'
import IndicatorGrid from '../components/IndicatorGrid.vue'
import MilestoneList from '../components/MilestoneList.vue'
import NordesteGoalCard from '../components/NordesteGoalCard.vue'
import PendingList from '../components/PendingList.vue'
import StageTimeline from '../components/StageTimeline.vue'
import TicketsChart from '../components/TicketsChart.vue'
import { useOverview } from '../composables/useOverview'
import locale from '../locales/pt-br'

const { current, role, isReadOnly } = useEdition()
const { overview, state, load } = useOverview()

watch(() => current.value?.id, (id) => {
  if (id) load(id)
}, { immediate: true })
</script>

<template>
  <section class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl leading-tight font-semibold">{{ locale.title }}</h1>
        <p v-if="overview" class="text-(--ph-ink-2)">{{ overview.subtitle }}</p>
      </div>
      <div v-if="role === 'organizador' && !isReadOnly" class="flex flex-wrap gap-3">
        <PhButton variant="ghost" to="/configuracoes">{{ locale.adjustEdition }}</PhButton>
        <PhButton to="/ingressos">{{ locale.createTicket }}</PhButton>
      </div>
    </div>

    <PhEmptyState v-if="!current" :title="locale.noEdition">
      <PhButton to="/comunidades">{{ locale.chooseEdition }}</PhButton>
    </PhEmptyState>

    <p v-else-if="state === 'loading' && !overview" role="status" aria-live="polite" class="flex items-center gap-2 text-(--ph-ink-2)">
      <span class="loading loading-spinner loading-sm" aria-hidden="true" />{{ locale.loading }}
    </p>

    <div v-else-if="state === 'error'" role="alert" class="alert alert-error alert-soft">
      <span>{{ locale.loadError }}</span>
      <PhButton variant="ghost" @click="load(current.id)">{{ locale.retry }}</PhButton>
    </div>

    <template v-else-if="overview">
      <StageTimeline :steps="overview.steps" :days-until-event="overview.daysUntilEvent" />
      <IndicatorGrid :indicators="overview.indicators" />
      <div class="grid gap-6 xl:grid-cols-[3fr_2fr]">
        <TicketsChart :weeks="overview.ticketsPerWeek" />
        <PendingList :items="overview.pending" />
      </div>
      <div class="grid gap-6 xl:grid-cols-2">
        <NordesteGoalCard v-if="overview.nordeste" :goal="overview.nordeste" />
        <MilestoneList :milestones="overview.milestones" />
      </div>
    </template>
  </section>
</template>
