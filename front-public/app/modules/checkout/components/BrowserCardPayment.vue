<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import CheckoutField from './CheckoutField.vue'
import InstallmentsSelect from './InstallmentsSelect.vue'
import { loadMercadoPago, type MercadoPagoInstance, type MpField } from '../services/mercadoPago'
import { formatCents, isCpf, maskCpf } from '../composables/format'
import locale from '../locales/pt-br'
import type { CardDetails } from '../types'

const props = defineProps<{ publicKey: string, totalCents: number, maxInstallments: number, busy: boolean }>()
const emit = defineEmits<{ pay: [card: CardDetails] }>()

const state = ref<'loading' | 'ready' | 'error'>('loading')
const holder = ref('')
const documentNumber = ref('')
const installments = ref(1)
const options = ref<Array<{ installments: number, amountCents: number }>>([{ installments: 1, amountCents: props.totalCents }])
const brand = ref<string | null>(null)
const issuer = ref<string | null>(null)
const errors = ref<{ holder: string | null, document: string | null, card: string | null }>({ holder: null, document: null, card: null })
const tokenizing = ref(false)
let mp: MercadoPagoInstance | null = null
const fields: MpField[] = []

const cpf = computed({ get: () => documentNumber.value, set: (value: string) => { documentNumber.value = maskCpf(value) } })

async function onBin(bin: string | undefined) {
  if (!mp || !bin) return
  try {
    const [plan] = await mp.getInstallments({ amount: (props.totalCents / 100).toFixed(2), bin, paymentTypeId: 'credit_card' })
    if (!plan) return
    brand.value = plan.payment_method_id
    issuer.value = plan.issuer?.id ? String(plan.issuer.id) : null
    options.value = plan.payer_costs
      .filter((cost) => cost.installments <= props.maxInstallments)
      .map((cost) => ({ installments: cost.installments, amountCents: Math.round(cost.installment_amount * 100) }))
    if (!options.value.some((option) => option.installments === installments.value)) installments.value = 1
  } catch {
    brand.value = null
  }
}

onMounted(async () => {
  try {
    const MercadoPago = await loadMercadoPago()
    mp = new MercadoPago(props.publicKey, { locale: 'pt-BR' })
    fields.push(mp.fields.create('cardNumber', { placeholder: '0000 0000 0000 0000' }).mount('mp-card-number').on('binChange', ({ bin }) => onBin(bin)))
    fields.push(mp.fields.create('expirationDate', { placeholder: 'MM/AA' }).mount('mp-card-expiry'))
    fields.push(mp.fields.create('securityCode', { placeholder: '123' }).mount('mp-card-cvv'))
    state.value = 'ready'
  } catch {
    state.value = 'error'
  }
})

onBeforeUnmount(() => fields.forEach((field) => field.unmount()))

async function pay() {
  errors.value = {
    holder: holder.value.trim().length < 2 ? locale.requiredField : null,
    document: isCpf(documentNumber.value) ? null : locale.cpfInvalid,
    card: null,
  }
  if (errors.value.holder || errors.value.document || !mp || props.busy || tokenizing.value) return
  tokenizing.value = true
  try {
    const token = await mp.fields.createCardToken({ cardholderName: holder.value.trim(), identificationType: 'CPF', identificationNumber: documentNumber.value.replace(/\D/g, '') })
    emit('pay', { token: token.id, brand: brand.value, issuer: issuer.value, installments: installments.value })
  } catch {
    errors.value = { ...errors.value, card: locale.cardIncomplete }
  } finally {
    tokenizing.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-3" data-testid="browser-card">
    <p class="text-sm text-(--ph-ink-2)">{{ locale.cardSecure }}</p>
    <p v-if="state === 'loading'" role="status" class="text-sm">{{ locale.cardLoading }}</p>
    <p v-else-if="state === 'error'" role="alert" class="alert alert-error alert-soft text-sm">{{ locale.cardSdkError }}</p>
    <form v-show="state === 'ready'" class="flex flex-col gap-3" novalidate @submit.prevent="pay">
      <div class="flex flex-col gap-1.5">
        <span id="mp-card-number-label" class="text-sm font-medium">{{ locale.cardNumber }}</span>
        <div id="mp-card-number" class="input h-11 w-full" role="group" aria-labelledby="mp-card-number-label" />
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1.5">
          <span id="mp-card-expiry-label" class="text-sm font-medium">{{ locale.cardExpiry }}</span>
          <div id="mp-card-expiry" class="input h-11 w-full" role="group" aria-labelledby="mp-card-expiry-label" />
        </div>
        <div class="flex flex-col gap-1.5">
          <span id="mp-card-cvv-label" class="text-sm font-medium">{{ locale.cardCvv }}</span>
          <div id="mp-card-cvv" class="input h-11 w-full" role="group" aria-labelledby="mp-card-cvv-label" />
        </div>
      </div>
      <p v-if="errors.card" role="alert" class="text-xs text-error">{{ errors.card }}</p>
      <CheckoutField id="card-holder" v-model="holder" :label="locale.cardHolder" autocomplete="cc-name" required :error="errors.holder" />
      <CheckoutField id="card-document" v-model="cpf" :label="locale.cardDocument" inputmode="numeric" required :error="errors.document" />
      <InstallmentsSelect id="card-installments" v-model="installments" :options="options" />
      <button type="submit" class="btn btn-primary w-full" :disabled="busy || tokenizing" :aria-busy="busy || tokenizing ? 'true' : undefined">
        <span v-if="busy || tokenizing" class="loading loading-spinner loading-sm" aria-hidden="true" />
        {{ busy || tokenizing ? locale.processing : locale.payCard.replace('{valor}', formatCents(totalCents)) }}
      </button>
    </form>
  </div>
</template>
