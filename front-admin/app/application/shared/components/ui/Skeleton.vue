<script setup lang="ts">
import locale from '@/application/locales/pt-br/ui'

withDefaults(defineProps<{ lines?: number }>(), { lines: 3 })
const widths = ['w-3/4', 'w-full', 'w-5/6', 'w-2/3', 'w-1/2']
</script>

<template>
  <div role="status" class="flex flex-col gap-3" aria-busy="true">
    <span class="sr-only">{{ locale.loading }}</span>
    <div
      v-for="n in lines"
      :key="n"
      class="skeleton h-3.5"
      :class="widths[(n - 1) % widths.length]"
      aria-hidden="true"
    />
  </div>
</template>
