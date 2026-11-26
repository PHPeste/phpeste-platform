<script setup lang="ts">
import locale from '../locales/pt-br'
import type { Receiver } from '../types'

defineProps<{ receiver: Receiver, editionName: string }>()
</script>

<template>
  <div class="flex flex-col gap-1 rounded-box bg-(--ph-surface-2,var(--color-base-200)) p-3 text-sm" data-testid="receiver">
    <p class="font-medium">{{ (receiver.holderType === 'company' ? locale.receiverCompany : locale.receiverIndividual).replace('{nome}', receiver.holderName).replace('{edicao}', editionName) }}</p>
    <p class="text-xs text-(--ph-ink-3)">{{ locale.receiverHint }}</p>
    <p v-if="receiver.sandbox" class="badge badge-warning badge-soft badge-sm">{{ locale.sandbox }}</p>
  </div>
</template>
