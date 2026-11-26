<script setup lang="ts">
import { formatCents } from '../composables/format'
import locale from '../locales/pt-br'
import type { SelectedLine } from '../types'

defineProps<{ lines: SelectedLine[], subtotalCents: number, discountCents: number, couponCode: string | null, totalCents: number, feePending: boolean }>()
</script>

<template>
  <section class="flex flex-col gap-3 rounded-box border border-base-300 bg-base-100 p-4" aria-labelledby="summary-title" data-testid="order-summary">
    <h2 id="summary-title" class="text-lg font-semibold">{{ locale.summary }}</h2>
    <p v-if="!lines.length" class="text-sm text-(--ph-ink-3)">{{ locale.nothingSelected }}</p>
    <dl v-else class="flex flex-col gap-2 text-sm">
      <div v-for="line in lines" :key="line.type.id" class="flex justify-between gap-3">
        <dt>{{ locale.line.replace('{qtd}', String(line.quantity)).replace('{nome}', line.type.name) }}</dt>
        <dd class="tabular-nums">{{ formatCents(line.subtotalCents) }}</dd>
      </div>
      <div class="flex justify-between gap-3 border-t border-base-300 pt-2">
        <dt>{{ locale.subtotal }}</dt>
        <dd class="tabular-nums">{{ formatCents(subtotalCents) }}</dd>
      </div>
      <div v-if="discountCents > 0" class="flex justify-between gap-3 text-success">
        <dt>{{ locale.discount.replace('{codigo}', couponCode ?? '') }}</dt>
        <dd class="tabular-nums">−{{ formatCents(discountCents) }}</dd>
      </div>
      <div v-if="feePending" class="flex justify-between gap-3 text-(--ph-ink-2)">
        <dt>{{ locale.fee }}</dt>
        <dd>{{ locale.feeLater }}</dd>
      </div>
      <div class="flex justify-between gap-3 border-t border-base-300 pt-2 text-base font-semibold">
        <dt>{{ feePending ? locale.totalBeforeFee : locale.total }}</dt>
        <dd class="tabular-nums" data-testid="summary-total">{{ formatCents(totalCents) }}</dd>
      </div>
    </dl>
    <slot />
  </section>
</template>
