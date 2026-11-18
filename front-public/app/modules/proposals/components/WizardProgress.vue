<script setup lang="ts">
import locale from '../locales/pt-br'

defineProps<{ step: number }>()
</script>

<template>
  <div class="flex flex-col gap-2">
    <p class="text-sm font-medium text-(--ph-ink-2)">{{ locale.stepOf.replace('{n}', String(step)).replace('{nome}', locale.steps[step] ?? '') }}</p>
    <progress class="progress progress-primary w-full" :value="step" max="3" :aria-label="locale.stepOf.replace('{n}', String(step)).replace('{nome}', locale.steps[step] ?? '')" />
    <ol class="hidden gap-4 text-xs text-(--ph-ink-3) sm:flex">
      <li v-for="n in 3" :key="n" :class="{ 'font-semibold text-(--ph-ink)': n === step }" :aria-current="n === step ? 'step' : undefined">{{ n }}. {{ locale.steps[n] }}</li>
    </ol>
  </div>
</template>
