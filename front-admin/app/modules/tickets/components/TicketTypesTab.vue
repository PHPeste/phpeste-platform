<script setup lang="ts">
import { ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import type { BadgeStatus } from '@/application/shared/components/ui/Badge.vue'
import BatchModal from './BatchModal.vue'
import TicketTypeModal from './TicketTypeModal.vue'
import { formatCents, formatDateTime, netFor } from '../composables/money'
import locale from '../locales/pt-br'
import type { Batch, BatchInput, MutationResult, TicketType, TicketTypeInput } from '../types'

const props = defineProps<{
  types: TicketType[]
  feeBasisPoints: number
  disabled: boolean
  saveType: (type: TicketType | null, input: TicketTypeInput) => Promise<MutationResult>
  removeType: (type: TicketType) => Promise<MutationResult>
  moveType: (type: TicketType, direction: -1 | 1) => Promise<MutationResult>
  saveBatch: (type: TicketType, batch: Batch | null, input: BatchInput) => Promise<MutationResult>
  closeBatch: (batch: Batch) => Promise<MutationResult>
  removeBatch: (batch: Batch) => Promise<MutationResult>
}>()

const toast = useToast()
const typeOpen = ref(false)
const editingType = ref<TicketType | null>(null)
const batchOpen = ref(false)
const batchType = ref<TicketType | null>(null)
const editingBatch = ref<Batch | null>(null)
const pending = ref<{ action: 'close' | 'remove', batch: Batch } | null>(null)
const busy = ref(false)

const STATUS_TONE: Record<Batch['status'], BadgeStatus> = { on_sale: 'ok', scheduled: 'info', sold_out: 'warn', ended: 'neutral' }

function openType(type: TicketType | null) {
  editingType.value = type
  typeOpen.value = true
}

function openBatch(type: TicketType, batch: Batch | null) {
  batchType.value = type
  editingBatch.value = batch
  batchOpen.value = true
}

function period(batch: Batch): string {
  const from = formatDateTime(batch.startsAt)
  const to = formatDateTime(batch.endsAt)
  if (from && to) return locale.fromTo.replace('{from}', from).replace('{to}', to)
  if (from) return locale.from.replace('{from}', from)
  if (to) return locale.until.replace('{to}', to)
  return locale.untilSoldOut
}

async function reorder(type: TicketType, direction: -1 | 1) {
  const result = await props.moveType(type, direction)
  if (!result.ok) toast.error(result.message)
}

async function confirmPending() {
  if (!pending.value) return
  const { action, batch } = pending.value
  busy.value = true
  const result = action === 'close' ? await props.closeBatch(batch) : await props.removeBatch(batch)
  busy.value = false
  pending.value = null
  if (result.ok) toast.success((action === 'close' ? locale.batchClosed : locale.batchRemoved).replace('{nome}', batch.name))
  else toast.error(result.message)
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div v-if="types.length && !disabled" class="flex justify-end">
      <PhButton @click="openType(null)">{{ locale.newType }}</PhButton>
    </div>

    <PhCard v-if="!types.length">
      <PhEmptyState :title="locale.noTypes" :description="locale.noTypesHint">
        <PhButton v-if="!disabled" @click="openType(null)">{{ locale.newType }}</PhButton>
      </PhEmptyState>
    </PhCard>

    <PhCard v-for="(type, index) in types" :key="type.id">
      <article class="flex flex-col gap-3" :aria-labelledby="`type-${type.id}`">
        <header class="flex flex-wrap items-start justify-between gap-3">
          <div class="flex flex-col gap-1">
            <div class="flex flex-wrap items-center gap-2">
              <h2 :id="`type-${type.id}`" class="text-lg font-semibold">{{ type.name }}</h2>
              <PhBadge :status="type.visibility === 'public' ? 'neutral' : 'info'">{{ type.visibilityLabel }}</PhBadge>
            </div>
            <span class="text-sm text-(--ph-ink-2) tabular-nums">{{ locale.soldOfTotal.replace('{sold}', String(type.sold)).replace('{total}', String(type.quantity)) }}</span>
          </div>
          <div v-if="!disabled" class="flex flex-wrap gap-1">
            <PhButton variant="ghost" :disabled="index === 0" :aria-label="locale.moveUp.replace('{nome}', type.name)" @click="reorder(type, -1)">↑</PhButton>
            <PhButton variant="ghost" :disabled="index === types.length - 1" :aria-label="locale.moveDown.replace('{nome}', type.name)" @click="reorder(type, 1)">↓</PhButton>
            <PhButton variant="ghost" :aria-label="`${locale.edit} ${type.name}`" @click="openType(type)">{{ locale.edit }}</PhButton>
            <PhButton variant="secondary" @click="openBatch(type, null)">{{ locale.newBatch }}</PhButton>
          </div>
        </header>

        <p v-if="!type.batches.length" class="text-sm text-(--ph-ink-3)">{{ locale.noBatches }}</p>
        <PhTable v-else :caption="`${locale.batch}s de ${type.name}`">
          <thead>
            <tr>
              <th scope="col">{{ locale.batch }}</th>
              <th scope="col" class="text-right">{{ locale.price }}</th>
              <th scope="col" class="text-right">{{ locale.received }}</th>
              <th scope="col" class="text-right">{{ locale.sold }}</th>
              <th scope="col">{{ locale.period }}</th>
              <th scope="col">{{ locale.status }}</th>
              <th scope="col"><span class="sr-only">{{ locale.actions }}</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="batch in type.batches" :key="batch.id">
              <td>
                <b class="font-medium">{{ batch.name }}</b>
                <span v-if="batch.id === type.currentBatchId" class="ml-2 text-xs text-(--ph-ink-3)">({{ locale.current }})</span>
              </td>
              <td class="text-right tabular-nums">{{ formatCents(batch.priceCents) }}</td>
              <td class="text-right tabular-nums">{{ formatCents(netFor(batch.priceCents, feeBasisPoints, type.passFeeToBuyer).received) }}</td>
              <td class="text-right tabular-nums">{{ batch.sold }} / {{ batch.quantity }}</td>
              <td class="text-sm">{{ period(batch) }}</td>
              <td><PhBadge :status="STATUS_TONE[batch.status]">{{ batch.statusLabel }}</PhBadge></td>
              <td class="text-right">
                <div v-if="!disabled" class="flex justify-end gap-1">
                  <PhButton variant="ghost" :aria-label="`${locale.edit} ${batch.name} de ${type.name}`" @click="openBatch(type, batch)">{{ locale.edit }}</PhButton>
                  <PhButton v-if="batch.status === 'on_sale' || batch.status === 'scheduled'" variant="ghost" :aria-label="`${locale.close} ${batch.name} de ${type.name}`" @click="pending = { action: 'close', batch }">{{ locale.close }}</PhButton>
                  <PhButton v-if="batch.sold === 0" variant="ghost" :aria-label="`${locale.remove} ${batch.name} de ${type.name}`" @click="pending = { action: 'remove', batch }">{{ locale.remove }}</PhButton>
                </div>
              </td>
            </tr>
          </tbody>
        </PhTable>
      </article>
    </PhCard>

    <TicketTypeModal
      v-model="typeOpen"
      :type="editingType"
      :fee-basis-points="feeBasisPoints"
      :save="saveType"
      :remove="removeType"
      @saved="toast.success(locale.typeSaved.replace('{nome}', $event))"
      @removed="toast.success(locale.typeRemoved.replace('{nome}', $event))"
    />
    <BatchModal
      v-model="batchOpen"
      :type="batchType"
      :batch="editingBatch"
      :fee-basis-points="feeBasisPoints"
      :save="saveBatch"
      @saved="toast.success(locale.batchSaved.replace('{nome}', $event))"
    />
    <PhModal :model-value="pending !== null" :title="pending ? (pending.action === 'close' ? locale.closeBatchTitle : locale.removeBatchTitle).replace('{lote}', pending.batch.name) : ''" @update:model-value="pending = null">
      <p>{{ pending?.action === 'close' ? locale.closeBatchText : locale.removeBatchText }}</p>
      <template #footer>
        <PhButton variant="ghost" @click="pending = null">{{ locale.back }}</PhButton>
        <PhButton variant="danger" :loading="busy" @click="confirmPending">{{ pending?.action === 'close' ? locale.close : locale.remove }}</PhButton>
      </template>
    </PhModal>
  </div>
</template>
