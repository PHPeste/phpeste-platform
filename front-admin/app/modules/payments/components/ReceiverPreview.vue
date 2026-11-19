<script setup lang="ts">
import { computed } from 'vue'
import locale from '../locales/pt-br'
import type { HolderType } from '../types'

const props = defineProps<{ holderName: string, holderType: HolderType, methods: string[], maxInstallments: number, sandbox: boolean }>()

const methodNames = computed(() => props.methods.map((method) => {
  const name = locale.methodNames[method] ?? method
  return method === 'card' && props.maxInstallments > 1 ? `${name} (${locale.installmentsValue.replace('{n}', String(props.maxInstallments))})` : name
}))
</script>

<template>
  <section class="flex flex-col gap-2" data-testid="receiver-preview" :aria-label="locale.preview">
    <p class="text-sm font-medium text-(--ph-ink-2)" aria-hidden="true">{{ locale.preview }}</p>
    <div class="flex flex-col gap-3 rounded-box border border-(--ph-line) bg-(--ph-surface-2) p-4">
      <div>
        <p class="text-xs text-(--ph-ink-3)">{{ locale.previewPaying }}</p>
        <p class="font-semibold">{{ holderName.trim() || '—' }}</p>
        <p class="text-xs text-(--ph-ink-3)">{{ locale.holderTypes[holderType] }}</p>
      </div>
      <div>
        <p class="text-xs text-(--ph-ink-3)">{{ locale.previewMethods }}</p>
        <ul class="flex flex-wrap gap-1.5 pt-1">
          <li v-for="name in methodNames" :key="name"><PhBadge status="neutral" :dot="false">{{ name }}</PhBadge></li>
        </ul>
      </div>
      <p v-if="sandbox" class="text-xs text-(--ph-warn)">{{ locale.previewSandbox }}</p>
    </div>
  </section>
</template>
