<script setup lang="ts">
import { ref } from 'vue'
import { formatCents, formatShort } from '../composables/format'
import locale from '../locales/pt-br'
import type { Column, Deal, Stage } from '../types'

defineProps<{ columns: Column[], disabled: boolean }>()
const emit = defineEmits<{ move: [deal: Deal, stage: Stage] }>()

const dragging = ref<Deal | null>(null)
const over = ref<Stage | null>(null)
const stages = Object.entries(locale.stages).map(([value, label]) => ({ value: value as Stage, label }))

function start(event: DragEvent, deal: Deal) {
  dragging.value = deal
  event.dataTransfer?.setData('text/plain', deal.id)
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
}

function drop(stage: Stage) {
  const deal = dragging.value
  dragging.value = null
  over.value = null
  if (deal && deal.stage !== stage) emit('move', deal, stage)
}

function pick(deal: Deal, event: Event) {
  const select = event.target as HTMLSelectElement
  const stage = select.value as Stage
  select.value = ''
  if (stage && stage !== deal.stage) emit('move', deal, stage)
}

const subtitle = (deal: Deal) => (deal.stage === 'paid' && deal.paidAt ? locale.paidOn.replace('{data}', formatShort(deal.paidAt)) : deal.lastContact?.summary ?? locale.noContact)
</script>

<template>
  <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" data-testid="sponsor-board">
    <section
      v-for="column in columns"
      :key="column.stage"
      class="flex min-h-48 flex-col gap-3 rounded-(--radius-box) border border-(--ph-line) bg-(--ph-surface-2) p-3 transition-colors"
      :class="{ 'border-(--ph-brand) bg-(--ph-surface)': over === column.stage }"
      :aria-labelledby="`col-${column.stage}`"
      :data-stage="column.stage"
      @dragover.prevent="over = column.stage"
      @dragleave="over === column.stage && (over = null)"
      @drop.prevent="drop(column.stage)"
    >
      <header class="flex items-baseline justify-between gap-2 px-1">
        <h3 :id="`col-${column.stage}`" class="font-semibold">{{ column.label }}</h3>
        <span class="text-xs text-(--ph-ink-3) tabular-nums" :aria-label="locale.columnCount.replace('{n}', String(column.cards.length)).replace('{valor}', formatCents(column.valueCents))">
          {{ column.cards.length }}
        </span>
      </header>
      <p v-if="dragging" class="sr-only" aria-live="polite">{{ locale.dropHere.replace('{etapa}', column.label) }}</p>

      <ul class="flex flex-col gap-2.5">
        <li
          v-for="deal in column.cards"
          :key="deal.id"
          class="flex flex-col gap-2 rounded-(--radius-box) border border-(--ph-line) bg-(--ph-surface) p-3 shadow-xs"
          :class="{ 'cursor-grab': !disabled, 'opacity-50': dragging?.id === deal.id }"
          :draggable="!disabled"
          data-testid="sponsor-card"
          @dragstart="start($event, deal)"
          @dragend="dragging = null; over = null"
        >
          <NuxtLink :to="`/patrocinadores/${deal.sponsorId}`" class="font-medium text-(--ph-ink) no-underline hover:underline" :aria-label="locale.open.replace('{empresa}', deal.sponsorName)">
            {{ deal.sponsorName }}
          </NuxtLink>
          <p class="text-xs text-(--ph-ink-2)">{{ deal.tierName ?? locale.noTier }} · {{ formatCents(deal.valueCents) }}</p>
          <p class="truncate text-xs text-(--ph-ink-3)">{{ subtitle(deal) }}</p>
          <select
            v-if="!disabled"
            class="select select-xs w-full"
            :aria-label="locale.moveLabel.replace('{empresa}', deal.sponsorName)"
            @change="pick(deal, $event)"
          >
            <option value="">{{ locale.moveTo }}</option>
            <option v-for="stage in stages" :key="stage.value" :value="stage.value" :disabled="stage.value === deal.stage">{{ stage.label }}</option>
          </select>
        </li>
      </ul>
      <p v-if="!column.cards.length" class="px-1 text-xs text-(--ph-ink-3)">{{ locale.emptyColumn }}</p>
    </section>
  </div>
</template>
