<script setup lang="ts">
import { computed, ref, useId } from 'vue'
import { fillText } from '../composables/useOverview'
import locale from '../locales/pt-br'
import type { WeekPoint } from '../types'

const props = defineProps<{ weeks: WeekPoint[] | null }>()

const WIDTH = 560
const HEIGHT = 200
const PAD = { top: 24, right: 8, bottom: 28, left: 36 }
const BAR_MAX = 24

const showTable = ref(false)
const active = ref<number | null>(null)
const summaryId = `chart-${useId()}`

const max = computed(() => Math.max(...(props.weeks ?? []).map((week) => week.value), 0))
const niceMax = computed(() => {
  if (max.value <= 0) return 10
  const magnitude = 10 ** Math.floor(Math.log10(max.value))
  return Math.ceil(max.value / magnitude) * magnitude
})
const ticks = computed(() => [0, niceMax.value / 2, niceMax.value])
const plotWidth = WIDTH - PAD.left - PAD.right
const plotHeight = HEIGHT - PAD.top - PAD.bottom
const band = computed(() => plotWidth / Math.max(props.weeks?.length ?? 1, 1))
const barWidth = computed(() => Math.min(BAR_MAX, band.value - 2))
const y = (value: number) => PAD.top + plotHeight - (value / niceMax.value) * plotHeight
const peakIndex = computed(() => (props.weeks ?? []).reduce((best, week, index, all) => (week.value > (all[best]?.value ?? -1) ? index : best), 0))

function barPath(index: number, value: number): string {
  const x = PAD.left + index * band.value + (band.value - barWidth.value) / 2
  const top = y(value)
  const bottom = PAD.top + plotHeight
  const radius = Math.min(4, (bottom - top) / 2, barWidth.value / 2)
  if (bottom - top <= 0) return ''
  return `M${x},${bottom} V${top + radius} Q${x},${top} ${x + radius},${top} H${x + barWidth.value - radius} Q${x + barWidth.value},${top} ${x + barWidth.value},${top + radius} V${bottom} Z`
}

const summary = computed(() => {
  const weeks = props.weeks ?? []
  if (weeks.length === 0) return ''
  const values = weeks.map((week) => week.value)
  return fillText(locale.chartSummary, {
    n: weeks.length,
    min: Math.min(...values),
    max: Math.max(...values),
    pico: weeks[peakIndex.value]?.label ?? '',
    total: values.reduce((sum, value) => sum + value, 0),
  })
})
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-4">
      <div class="flex flex-wrap items-baseline justify-between gap-2">
        <div>
          <h2 class="text-lg font-semibold">{{ locale.chartTitle }}</h2>
          <p v-if="weeks?.length" class="text-xs text-(--ph-ink-3)">{{ fillText(locale.chartSubtitle, { n: weeks.length }) }}</p>
        </div>
        <button v-if="weeks?.length" type="button" class="link text-sm text-(--ph-link)" :aria-pressed="showTable" @click="showTable = !showTable">
          {{ showTable ? locale.hideTable : locale.showTable }}
        </button>
      </div>

      <p v-if="!weeks || weeks.length === 0" class="py-10 text-center text-sm text-(--ph-ink-2)">{{ locale.chartEmpty }}</p>

      <table v-else-if="showTable" class="table">
        <caption class="sr-only">{{ locale.chartTitle }}</caption>
        <thead><tr><th scope="col">{{ locale.week }}</th><th scope="col" class="text-right">{{ locale.sold }}</th></tr></thead>
        <tbody>
          <tr v-for="week in weeks" :key="week.label"><th scope="row" class="font-normal">{{ week.label }}</th><td class="text-right tabular-nums">{{ week.value }}</td></tr>
        </tbody>
      </table>

      <figure v-else class="relative m-0">
        <svg :viewBox="`0 0 ${WIDTH} ${HEIGHT}`" class="h-auto w-full" role="img" :aria-labelledby="summaryId">
          <g aria-hidden="true">
            <g v-for="tick in ticks" :key="tick">
              <line :x1="PAD.left" :x2="WIDTH - PAD.right" :y1="y(tick)" :y2="y(tick)" stroke="var(--ph-line)" stroke-width="1" />
              <text :x="PAD.left - 6" :y="y(tick) + 4" text-anchor="end" class="fill-(--ph-ink-3) text-[11px]">{{ tick }}</text>
            </g>
            <text
              v-for="(week, index) in weeks"
              :key="`l-${week.label}`"
              :x="PAD.left + index * band + band / 2"
              :y="HEIGHT - 8"
              text-anchor="middle"
              class="fill-(--ph-ink-3) text-[11px]"
            >{{ week.label }}</text>
            <path v-for="(week, index) in weeks" :key="`b-${week.label}`" :d="barPath(index, week.value)" fill="var(--ph-brand)" :opacity="active === null || active === index ? 1 : 0.55" />
            <text
              v-if="weeks[peakIndex] && active === null"
              :x="PAD.left + peakIndex * band + band / 2"
              :y="y(weeks[peakIndex]!.value) - 6"
              text-anchor="middle"
              class="fill-(--ph-ink) text-[11px] font-semibold"
            >{{ weeks[peakIndex]!.value }}</text>
          </g>
          <rect
            v-for="(week, index) in weeks"
            :key="`h-${week.label}`"
            :x="PAD.left + index * band"
            :y="PAD.top"
            :width="band"
            :height="plotHeight"
            fill="transparent"
            tabindex="0"
            :aria-label="`${week.label}: ${week.value} ${locale.sold.toLowerCase()}`"
            class="cursor-default focus:outline-none"
            @mouseenter="active = index"
            @mouseleave="active = null"
            @focus="active = index"
            @blur="active = null"
          />
        </svg>
        <div
          v-if="active !== null && weeks[active]"
          class="pointer-events-none absolute -translate-x-1/2 rounded-md border border-(--ph-line) bg-(--ph-surface) px-2 py-1 text-xs shadow-sm"
          :style="{ left: `${((PAD.left + active * band + band / 2) / WIDTH) * 100}%`, top: '0' }"
          role="status"
        >
          <b class="font-semibold">{{ weeks[active]!.value }}</b> · {{ weeks[active]!.label }}
        </div>
        <figcaption :id="summaryId" class="sr-only">{{ summary }}</figcaption>
      </figure>
    </div>
  </PhCard>
</template>
