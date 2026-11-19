<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { centsToInput, formatCents, isoToLocal, netFor, parseReais } from '../composables/money'
import locale from '../locales/pt-br'
import type { Batch, BatchInput, MutationResult, SellOut, TicketType } from '../types'

const props = defineProps<{
  type: TicketType | null
  batch: Batch | null
  feeBasisPoints: number
  save: (type: TicketType, batch: Batch | null, input: BatchInput) => Promise<MutationResult>
}>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [name: string] }>()

const form = reactive({ name: '', price: '', quantity: 100 as number | string, startsAt: '', endsAt: '', onSellOut: 'next' as SellOut })
const busy = ref(false)
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)

watch(open, (value) => {
  if (!value) return
  const batch = props.batch
  Object.assign(form, {
    name: batch?.name ?? `${(props.type?.batches.length ?? 0) + 1}º lote`,
    price: batch ? centsToInput(batch.priceCents) : '',
    quantity: batch?.quantity ?? 100,
    startsAt: isoToLocal(batch?.startsAt ?? null),
    endsAt: isoToLocal(batch?.endsAt ?? null),
    onSellOut: batch?.onSellOut ?? 'next',
  })
  errors.value = {}
  message.value = null
})

const priceCents = computed(() => parseReais(form.price))
const net = computed(() => (priceCents.value === null ? null : netFor(priceCents.value, props.feeBasisPoints, props.type?.passFeeToBuyer ?? false)))
const feePercent = computed(() => `${(props.feeBasisPoints / 100).toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`)
const priceChanged = computed(() => props.batch !== null && priceCents.value !== null && priceCents.value !== props.batch.priceCents)
const sellOutOptions = Object.entries(locale.onSellOutOptions).map(([value, label]) => ({ value, label }))
const title = computed(() => (props.batch ? locale.editBatchTitle.replace('{lote}', props.batch.name) : locale.newBatchTitle.replace('{nome}', props.type?.name ?? '')))
const labels: Record<string, string> = { name: locale.batchName, price: locale.price, quantity: locale.quantity, startsAt: locale.startsAt, endsAt: locale.endsAt }
const fieldIds: Record<string, string> = { name: 'batch-name', price: 'batch-price', quantity: 'batch-quantity', startsAt: 'batch-starts', endsAt: 'batch-ends' }

async function submit() {
  const found: Record<string, string> = {}
  if (!form.name.trim()) found.name = locale.required
  if (priceCents.value === null) found.price = form.price.trim() ? locale.priceInvalid : locale.required
  if (form.startsAt && form.endsAt && form.endsAt <= form.startsAt) found.endsAt = locale.endsAfterStarts
  errors.value = found
  if (Object.keys(found).length || !props.type) return

  busy.value = true
  const result = await props.save(props.type, props.batch, { name: form.name, priceCents: priceCents.value!, quantity: Number(form.quantity), startsAt: form.startsAt, endsAt: form.endsAt, onSellOut: form.onSellOut })
  busy.value = false
  if (result.ok) {
    emit('saved', form.name.trim())
    open.value = false
    return
  }
  errors.value = result.fieldErrors
  message.value = Object.keys(result.fieldErrors).length ? null : result.message
}
</script>

<template>
  <PhModal v-model="open" :title="title">
    <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
    <form id="batch-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <PhInput id="batch-name" v-model="form.name" :label="locale.batchName" required :error="errors.name" />
      <div class="grid grid-cols-2 gap-3">
        <PhInput id="batch-price" v-model="form.price" :label="locale.price" :hint="locale.priceHint" required :error="errors.price" />
        <PhInput id="batch-quantity" v-model="form.quantity" type="number" :label="locale.quantity" required :error="errors.quantity" />
      </div>
      <p v-if="priceChanged && batch && batch.sold > 0" role="alert" class="alert alert-warning alert-soft text-sm" data-testid="price-change-warning">
        {{ locale.priceChangeWarning.replace('{n}', String(batch.sold)) }}
      </p>
      <section v-if="net" :aria-label="locale.netTitle" class="rounded-box bg-(--ph-surface-2) p-3 text-sm" aria-live="polite" data-testid="net-calculation">
        <p v-if="priceCents === 0">{{ locale.netFree }}</p>
        <dl v-else class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1">
          <dt>{{ locale.netBuyerPays }}</dt><dd class="text-right tabular-nums">{{ formatCents(net.buyerPays) }}</dd>
          <dt>{{ locale.netFee.replace('{pct}', feePercent) }}</dt><dd class="text-right tabular-nums">− {{ formatCents(net.fee) }}</dd>
          <dt class="font-semibold">{{ locale.netReceived }}</dt><dd class="text-right font-semibold tabular-nums">{{ formatCents(net.received) }}</dd>
        </dl>
      </section>
      <div class="grid grid-cols-2 gap-3">
        <PhInput id="batch-starts" v-model="form.startsAt" type="datetime-local" :label="locale.startsAt" :error="errors.startsAt" />
        <PhInput id="batch-ends" v-model="form.endsAt" type="datetime-local" :label="locale.endsAt" :error="errors.endsAt" />
      </div>
      <PhSelect v-model="form.onSellOut" :label="locale.onSellOut" :options="sellOutOptions" />
    </form>
    <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
      <PhButton type="submit" form="batch-form" :loading="busy">{{ locale.saveBatch }}</PhButton>
    </template>
  </PhModal>
</template>
