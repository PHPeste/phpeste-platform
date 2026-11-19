<script setup lang="ts">
import { computed } from 'vue'
import { formatCents, netFor } from '../composables/money'
import locale from '../locales/pt-br'
import type { Batch, Visibility } from '../types'

const props = defineProps<{
  name: string
  description: string
  visibility: Visibility
  showRemaining: boolean
  passFeeToBuyer: boolean
  feeBasisPoints: number
  batch: Batch | null
}>()

const price = computed(() => (props.batch ? netFor(props.batch.priceCents, props.feeBasisPoints, props.passFeeToBuyer) : null))
const soldOut = computed(() => props.batch !== null && props.batch.remaining === 0)
</script>

<template>
  <section :aria-label="locale.previewTitle" class="flex flex-col gap-2">
    <h3 class="text-sm font-semibold text-(--ph-ink-2)">{{ locale.previewTitle }}</h3>
    <article class="flex flex-col gap-3 rounded-box border border-(--ph-line) bg-(--ph-surface) p-4" data-testid="ticket-preview">
      <div class="flex items-start justify-between gap-3">
        <div class="flex flex-col gap-1">
          <strong class="text-base">{{ name.trim() || locale.previewName }}</strong>
          <span v-if="batch" class="text-xs text-(--ph-ink-3)">{{ batch.name }}</span>
        </div>
        <PhBadge v-if="visibility === 'code'" status="info">{{ locale.previewOnlyCode }}</PhBadge>
      </div>
      <p v-if="description.trim()" class="text-sm text-(--ph-ink-2) whitespace-pre-line">{{ description }}</p>
      <div class="flex items-end justify-between gap-3 border-t border-(--ph-line) pt-3">
        <template v-if="batch && price">
          <div class="flex flex-col">
            <span class="text-xl font-semibold tabular-nums">{{ batch.priceCents === 0 ? 'Grátis' : formatCents(price.buyerPays) }}</span>
            <span v-if="passFeeToBuyer && price.fee > 0" class="text-xs text-(--ph-ink-3)">{{ locale.previewFee.replace('{fee}', formatCents(price.fee)) }}</span>
          </div>
          <PhBadge v-if="soldOut" status="danger">{{ locale.previewSoldOut }}</PhBadge>
          <span v-else-if="showRemaining" class="text-xs text-(--ph-ink-2)">{{ locale.previewRemaining.replace('{n}', String(batch.remaining)) }}</span>
        </template>
        <span v-else class="text-sm text-(--ph-ink-3)">{{ locale.previewNoBatch }}</span>
      </div>
    </article>
  </section>
</template>
