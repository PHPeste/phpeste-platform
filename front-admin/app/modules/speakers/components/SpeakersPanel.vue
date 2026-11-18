<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import CoverageGrid from './CoverageGrid.vue'
import PromotionsCard from './PromotionsCard.vue'
import SpeakerKpis from './SpeakerKpis.vue'
import SpeakerTable from './SpeakerTable.vue'
import { useSpeakers } from '../composables/useSpeakers'
import locale from '../locales/pt-br'

const props = defineProps<{ editionId: string, readOnly: boolean }>()

const speakers = useSpeakers(props.editionId)
const { rows, summary, coverage, promotions, state, filter, search, filtered, counts, missingStates } = speakers
const toast = useToast()

const remindOpen = ref(false)
const reminding = ref(false)

onMounted(speakers.load)

async function remindAll() {
  reminding.value = true
  const result = await speakers.remindAll()
  reminding.value = false
  remindOpen.value = false
  if (typeof result === 'string') return toast.error(result)
  if (!result.reminded) return toast.info(locale.remindedNothing)
  toast.success(result.reminded === 1 ? locale.remindedOne : locale.reminded.replace('{n}', String(result.reminded)))
  if (result.skipped) toast.info(locale.remindedSkipped.replace('{n}', String(result.skipped)))
}
</script>

<template>
  <section class="flex flex-col gap-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl leading-tight font-semibold">{{ locale.title }}</h1>
        <p class="text-(--ph-ink-2)">{{ locale.subtitle }}</p>
      </div>
      <PhButton v-if="state === 'ready' && !readOnly" variant="ghost" :disabled="!summary?.awaiting" @click="remindOpen = true">{{ locale.remindAll }}</PhButton>
    </header>

    <PhPageSkeleton v-if="state === 'loading'" :kpis="4" :cards="2" :lines="5" />
    <PhLoadError v-else-if="state === 'error'" :title="locale.loadError" @retry="speakers.load" />

    <template v-else-if="summary">
      <SpeakerKpis :summary="summary" />
      <CoverageGrid :coverage="coverage" :missing="missingStates" />
      <PromotionsCard
        :groups="promotions"
        :disabled="readOnly"
        :promote="speakers.promote"
        @promoted="toast.success(locale.promoted.replace('{n}', String($event)))"
        @failed="toast.error($event)"
      />
      <SpeakerTable v-model:filter="filter" v-model:search="search" :rows="filtered" :total="rows.length" :counts="counts" />
    </template>

    <PhModal v-model="remindOpen" :title="locale.remindAllTitle">
      <p>{{ summary?.awaiting === 1 ? locale.remindAllTextOne : locale.remindAllText.replace('{n}', String(summary?.awaiting ?? 0)) }}</p>
      <template #footer>
        <PhButton variant="ghost" @click="remindOpen = false">{{ locale.cancel }}</PhButton>
        <PhButton :loading="reminding" @click="remindAll">{{ locale.send }}</PhButton>
      </template>
    </PhModal>
  </section>
</template>
