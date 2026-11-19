<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { REFUND_TONE } from './statusTone'
import { refundableCents } from '../composables/useOrders'
import { formatCents, parseReais } from '../composables/format'
import locale from '../locales/pt-br'
import type { Order, Refund, RefundInput, RefundResult } from '../types'

const props = defineProps<{ order: Order, refund: (input: RefundInput) => Promise<RefundResult> }>()
const open = defineModel<boolean>({ default: false })

type Step = 'form' | 'confirm' | 'result'

const form = reactive({ kind: 'full' as 'full' | 'partial', amount: '', reason: 'buyer_request', note: '', cancelTickets: false, itemIds: [] as string[] })
const step = ref<Step>('form')
const checked = ref(false)
const busy = ref(false)
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)
const result = ref<Refund | null>(null)

const maxCents = computed(() => refundableCents(props.order))
const activeItems = computed(() => props.order.items.filter((item) => !item.cancelled))
const reasonOptions = Object.entries(locale.reasons).map(([value, label]) => ({ value, label }))
const labels: Record<string, string> = { amount: locale.amount, reason: locale.reason, items: locale.chooseTickets, note: locale.note }
const fieldIds: Record<string, string> = { amount: 'refund-amount', reason: 'refund-reason', note: 'refund-note' }

watch(open, (value) => {
  if (!value) return
  Object.assign(form, { kind: 'full', amount: '', reason: 'buyer_request', note: '', cancelTickets: false, itemIds: activeItems.value.map((item) => item.id) })
  step.value = 'form'
  checked.value = false
  errors.value = {}
  message.value = null
  result.value = null
})

const amountCents = computed(() => (form.kind === 'full' ? maxCents.value : parseReais(form.amount)))
const allItems = computed(() => form.itemIds.length === activeItems.value.length)

function toggleItem(id: string, value: boolean) {
  form.itemIds = value ? [...form.itemIds, id] : form.itemIds.filter((itemId) => itemId !== id)
}

function review() {
  const found: Record<string, string> = {}
  const cents = amountCents.value
  if (cents === null || cents <= 0 || cents > maxCents.value) found.amount = locale.amountInvalid.replace('{valor}', formatCents(maxCents.value))
  if (form.cancelTickets && form.itemIds.length === 0) found.items = locale.chooseTicketsError
  errors.value = found
  message.value = null
  if (Object.keys(found).length) return
  checked.value = false
  step.value = 'confirm'
}

async function confirm() {
  busy.value = true
  const response = await props.refund({
    amountCents: form.kind === 'full' ? null : amountCents.value,
    reason: form.reason,
    note: form.note,
    cancelTickets: form.cancelTickets,
    itemIds: form.cancelTickets && !allItems.value ? form.itemIds : null,
  })
  busy.value = false
  if (response.ok) {
    result.value = response.refund
    step.value = 'result'
    return
  }
  errors.value = response.fieldErrors
  message.value = Object.keys(response.fieldErrors).length ? null : response.message
  step.value = 'form'
}

const resultText = computed(() => {
  const refund = result.value
  if (!refund) return ''
  return locale.results[refund.status].replace('{valor}', formatCents(refund.amountCents)).replace('{motivo}', refund.failureReason ?? '—')
})

const title = computed(() => ({
  form: locale.refundTitle.replace('{n}', String(props.order.number)),
  confirm: locale.confirmTitle,
  result: locale.resultTitle,
})[step.value])
</script>

<template>
  <PhModal v-model="open" :title="title">
    <template v-if="step === 'form'">
      <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
      <form id="refund-form" class="flex flex-col gap-4" novalidate @submit.prevent="review">
        <p class="text-sm text-(--ph-ink-2)">{{ locale.refundable.replace('{valor}', formatCents(maxCents)) }}</p>
        <fieldset class="flex flex-col gap-2">
          <legend class="text-sm font-medium">{{ locale.refundKind }}</legend>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.kind" type="radio" class="radio radio-sm" value="full" name="refund-kind">
            {{ locale.refundFull.replace('{valor}', formatCents(maxCents)) }}
          </label>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.kind" type="radio" class="radio radio-sm" value="partial" name="refund-kind">
            {{ locale.refundPartial }}
          </label>
        </fieldset>
        <PhInput v-if="form.kind === 'partial'" id="refund-amount" v-model="form.amount" :label="locale.amount" placeholder="0,00" required :error="errors.amount" />
        <p v-else-if="errors.amount" class="text-sm text-(--ph-danger)">{{ errors.amount }}</p>
        <PhSelect id="refund-reason" v-model="form.reason" :label="locale.reason" :options="reasonOptions" required :error="errors.reason" />
        <PhTextarea id="refund-note" v-model="form.note" :label="locale.note" :hint="locale.noteHint" :rows="2" :error="errors.note" />
        <PhCheckbox v-model="form.cancelTickets" :label="locale.cancelTickets" :hint="locale.cancelTicketsHint" :disabled="!activeItems.length" />
        <fieldset v-if="form.cancelTickets" class="flex flex-col gap-2 pl-6">
          <legend class="text-sm font-medium">{{ locale.chooseTickets }}</legend>
          <PhCheckbox v-for="item in activeItems" :key="item.id" :model-value="form.itemIds.includes(item.id)" :label="`${item.attendeeName} · ${item.ticketTypeName}`" @update:model-value="toggleItem(item.id, $event)" />
          <p v-if="errors.items" class="text-sm text-(--ph-danger)">{{ errors.items }}</p>
        </fieldset>
        <div role="note" class="alert alert-warning alert-soft flex flex-col items-start gap-1 text-sm" data-testid="refund-fee-warning">
          <strong>{{ locale.feeWarningTitle }}</strong>
          <span>{{ locale.feeWarning.replace('{taxa}', formatCents(order.gatewayFeeCents)) }}</span>
        </div>
      </form>
    </template>

    <div v-else-if="step === 'confirm'" class="flex flex-col gap-3" data-testid="refund-confirm">
      <p>{{ locale.confirmText.replace('{valor}', formatCents(amountCents ?? 0)).replace('{nome}', order.buyerName) }}</p>
      <p class="text-sm text-(--ph-ink-2)">{{ form.cancelTickets ? locale.confirmTickets.replace('{n}', String(form.itemIds.length)) : locale.keepTickets }}</p>
      <PhCheckbox v-model="checked" :label="locale.confirmCheck" />
    </div>

    <div v-else-if="result" class="flex flex-col gap-3" data-testid="refund-result">
      <PhBadge :status="REFUND_TONE[result.status]">{{ result.statusLabel }}</PhBadge>
      <p role="status">{{ resultText }}</p>
    </div>

    <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>

    <template #footer>
      <template v-if="step === 'form'">
        <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
        <PhButton type="submit" form="refund-form">{{ locale.review }}</PhButton>
      </template>
      <template v-else-if="step === 'confirm'">
        <PhButton variant="ghost" :disabled="busy" @click="step = 'form'">{{ locale.back }}</PhButton>
        <PhButton variant="danger" :loading="busy" :disabled="!checked" @click="confirm">{{ locale.confirmRefund.replace('{valor}', formatCents(amountCents ?? 0)) }}</PhButton>
      </template>
      <PhButton v-else @click="open = false">{{ locale.close }}</PhButton>
    </template>
  </PhModal>
</template>
