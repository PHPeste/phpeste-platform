<script setup lang="ts">
import locale from '@/application/locales/pt-br/ui'

withDefaults(defineProps<{ kpis?: number, cards?: number, lines?: number, columns?: 1 | 2 }>(), { kpis: 0, cards: 1, lines: 4, columns: 1 })
const widths = ['w-3/4', 'w-full', 'w-5/6', 'w-2/3', 'w-1/2']
</script>

<template>
  <div role="status" aria-busy="true" class="flex flex-col gap-6" data-testid="page-skeleton">
    <span class="sr-only">{{ locale.loading }}</span>
    <div v-if="kpis" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-hidden="true">
      <div v-for="n in kpis" :key="`kpi-${n}`" class="card flex flex-col gap-3 border border-base-300 bg-base-100 px-6 py-5">
        <div class="skeleton h-3 w-1/2" />
        <div class="skeleton h-8 w-1/3" />
      </div>
    </div>
    <div class="grid items-start gap-6" :class="columns === 2 ? 'lg:grid-cols-2' : ''" aria-hidden="true">
      <div v-for="n in cards" :key="`card-${n}`" class="card flex flex-col gap-3 border border-base-300 bg-base-100 p-6">
        <div class="skeleton h-5 w-1/3" />
        <div v-for="line in lines" :key="line" class="skeleton h-3.5" :class="widths[(line + n) % widths.length]" />
      </div>
    </div>
  </div>
</template>
