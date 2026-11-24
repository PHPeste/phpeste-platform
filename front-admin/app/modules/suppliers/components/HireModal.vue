<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { formatCents, parseReais } from '../composables/format'
import locale from '../locales/pt-br'
import type { MutationResult, SupplierLink } from '../types'

const props = defineProps<{ supplier: SupplierLink, hire: (quoteId: string | null, contractedCents: number | null) => Promise<MutationResult> }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [] }>()

const quoteId = ref('')
const value = ref('')
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)
const busy = ref(false)
const usable = computed(() => props.supplier.quotes.filter((quote) => quote.status !== 'rejected'))
const options = computed(() => [
  ...usable.value.map((quote) => ({ value: quote.id, label: `${quote.description} · ${formatCents(quote.amountCents)}` })),
  { value: '', label: locale.hireNoQuote },
])
const labels: Record<string, string> = { quote: locale.hireQuote, contracted: locale.hireValue }
const fieldIds: Record<string, string> = { quote: 'hire-quote', contracted: 'hire-value' }

watch(open, (isOpen) => {
  if (!isOpen) return
  const cheapest = [...usable.value].sort((a, b) => a.amountCents - b.amountCents)[0]
  quoteId.value = props.supplier.acceptedQuoteId ?? cheapest?.id ?? ''
  value.value = ''
  errors.value = {}
  message.value = null
})

async function submit() {
  const cents = parseReais(value.value)
  if (value.value.trim() !== '' && cents === null) {
    errors.value = { contracted: locale.hireValueInvalid }
    return
  }
  if (!quoteId.value && cents === null) {
    errors.value = { contracted: locale.required }
    return
  }
  busy.value = true
  const result = await props.hire(quoteId.value || null, cents)
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
  <PhModal v-model="open" :title="locale.hireTitle.replace('{nome}', supplier.name)">
    <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
    <form id="hire-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <PhSelect id="hire-quote" v-model="quoteId" :label="locale.hireQuote" :options="options" :error="errors.quote" />
      <PhInput id="hire-value" v-model="value" :label="locale.hireValue" :hint="locale.hireValueHint" :required="!quoteId" :error="errors.contracted" />
    </form>
    <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
      <PhButton type="submit" form="hire-form" :loading="busy">{{ locale.hire }}</PhButton>
    </template>
  </PhModal>
</template>
