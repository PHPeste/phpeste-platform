<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { parseReais, today } from '../composables/format'
import locale from '../locales/pt-br'
import type { MutationResult, QuoteInput } from '../types'

const props = defineProps<{ record: (input: QuoteInput) => Promise<MutationResult> }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [] }>()

const form = reactive({ description: '', amount: '', receivedOn: '', validUntil: '' })
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)
const busy = ref(false)
const labels: Record<string, string> = { description: locale.quoteDescription, amount: locale.quoteAmount, receivedOn: locale.quoteReceived, validUntil: locale.quoteValidUntil }
const fieldIds: Record<string, string> = { description: 'quote-description', amount: 'quote-amount', receivedOn: 'quote-received', validUntil: 'quote-valid' }

watch(open, (value) => {
  if (!value) return
  Object.assign(form, { description: '', amount: '', receivedOn: today(), validUntil: '' })
  errors.value = {}
  message.value = null
})

async function submit() {
  const amount = parseReais(form.amount)
  const found: Record<string, string> = {}
  if (form.description.trim().length < 2) found.description = locale.required
  if (amount === null) found.amount = form.amount.trim() ? locale.quoteAmountInvalid : locale.required
  if (!form.receivedOn) found.receivedOn = locale.required
  errors.value = found
  if (Object.keys(found).length) return

  busy.value = true
  const result = await props.record({ description: form.description, amountCents: amount!, receivedOn: form.receivedOn, validUntil: form.validUntil })
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
  <PhModal v-model="open" :title="locale.quoteTitle">
    <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
    <form id="quote-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <PhInput id="quote-description" v-model="form.description" :label="locale.quoteDescription" :hint="locale.quoteDescriptionHint" required :error="errors.description" />
      <div class="grid gap-3 sm:grid-cols-3">
        <PhInput id="quote-amount" v-model="form.amount" :label="locale.quoteAmount" required :error="errors.amount" />
        <PhInput id="quote-received" v-model="form.receivedOn" type="date" :label="locale.quoteReceived" required :error="errors.receivedOn" />
        <PhInput id="quote-valid" v-model="form.validUntil" type="date" :label="locale.quoteValidUntil" :error="errors.validUntil" />
      </div>
    </form>
    <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
      <PhButton type="submit" form="quote-form" :loading="busy">{{ locale.save }}</PhButton>
    </template>
  </PhModal>
</template>
