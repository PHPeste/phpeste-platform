<script setup lang="ts">
import { computed } from 'vue'
import locale from '../locales/pt-br'
import type { ContrastResult } from '../types'

const props = defineProps<{ results: ContrastResult[] }>()

const failing = computed(() => props.results.filter((result) => !result.passes))
const sorted = computed(() => [...failing.value, ...props.results.filter((result) => result.passes)])
const ratio = (value: number) => value.toFixed(2).replace('.', ',')
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-3">
      <h2 class="text-lg font-semibold">{{ locale.contrastTitle }}</h2>
      <p role="status" aria-live="polite" class="text-sm" :class="failing.length ? 'font-medium text-error' : 'text-(--ph-ink-2)'">
        {{ failing.length
          ? (failing.length === 1 ? locale.contrastFailingOne : locale.contrastFailing.replace('{n}', String(failing.length)))
          : locale.contrastAllOk.replace('{n}', String(results.length)) }}
      </p>
      <details :open="failing.length > 0">
        <summary class="min-h-9 cursor-pointer py-1.5 text-sm font-medium">{{ locale.contrastShowAll.replace('{n}', String(results.length)) }}</summary>
        <ul class="flex flex-col">
          <li v-for="result in sorted" :key="`${result.foreground}-${result.background}`" class="flex items-center justify-between gap-3 border-t border-(--ph-line) py-2 text-sm first:border-t-0">
            <span>{{ result.label }}</span>
            <span class="flex shrink-0 items-center gap-2">
              <span class="tabular-nums">{{ ratio(result.ratio) }}:1</span>
              <PhBadge :status="result.passes ? 'ok' : 'danger'" :dot="false">
                {{ result.passes ? result.level : `${locale.levelFail} (${locale.contrastMinimum.replace('{n}', ratio(result.minimum))})` }}
              </PhBadge>
            </span>
          </li>
        </ul>
      </details>
    </div>
  </PhCard>
</template>
