<script setup lang="ts">
import locale from '../locales/pt-br'
import type { Indicator } from '../types'

defineProps<{ indicators: Indicator[] }>()
</script>

<template>
  <section :aria-label="locale.indicators" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <PhKpi v-for="indicator in indicators" :key="indicator.label" :label="indicator.label" :value="indicator.value" :hint="indicator.hint" />
  </section>
</template>
