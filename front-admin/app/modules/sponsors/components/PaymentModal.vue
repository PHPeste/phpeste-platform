<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { centsToInput, formatCents, parseReais, today } from '../composables/format'
import locale from '../locales/pt-br'
import type { Deal, MutationResult } from '../types'

const props = defineProps<{ deal: Deal, record: (amountCents: number, paidOn: string, note: string) => Promise<MutationResult> }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [] }>()

const form = reactive({ amount: '', paidOn: '', note: '' })
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)
const busy = ref(false)
const labels: Record<string, string> = { amount: locale.paymentAmount, paidOn: locale.paymentDate, note: locale.paymentNote }
const fieldIds: Record<string, string> = { amount: 'payment-amount', paidOn: 'payment-date', note: 'payment-note' }

watch(open, (value) => {
  if (!value) return
  Object.assign(form, { amount: props.deal.outstandingCents > 0 ? centsToInput(props.deal.outstandingCents) : '', paidOn: today(), note: '' })
  errors.value = {}
  message.value = null
})

async function submit() {
  const amount = parseReais(form.amount)
  const found: Record<string, string> = {}
  if (amount === null || amount <= 0) found.amount = form.amount.trim() ? locale.paymentAmountInvalid : locale.required
  if (!form.paidOn) found.paidOn = locale.required
  errors.value = found
  if (Object.keys(found).length) return

  busy.value = true
  const result = await props.record(amount!, form.paidOn, form.note)
  busy.value = false
  if (result.ok) {
    emit('saved')
    open.value = false
    return
  }
  errors.value = result.fieldErrors
  message.value = Object.keys(result.fieldErrors).length ? null : result.message
}
</script>

<template>
  <PhModal v-model="open" :title="locale.paymentTitle">
    <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
    <form id="payment-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <p class="text-sm text-(--ph-ink-2)">{{ locale.paymentHint.replace('{valor}', formatCents(deal.outstandingCents)) }}</p>
      <div class="grid grid-cols-2 gap-3">
        <PhInput id="payment-amount" v-model="form.amount" :label="locale.paymentAmount" required :error="errors.amount" />
        <PhInput id="payment-date" v-model="form.paidOn" type="date" :label="locale.paymentDate" required :error="errors.paidOn" />
      </div>
      <PhInput id="payment-note" v-model="form.note" :label="locale.paymentNote" :hint="locale.paymentNoteHint" :error="errors.note" />
    </form>
    <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
      <PhButton type="submit" form="payment-form" :loading="busy">{{ locale.recordPayment }}</PhButton>
    </template>
  </PhModal>
</template>
