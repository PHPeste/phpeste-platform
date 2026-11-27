<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import BrowserCardPayment from './BrowserCardPayment.vue'
import HostedCardPayment from './HostedCardPayment.vue'
import PixPayment from './PixPayment.vue'
import ReceiverNotice from './ReceiverNotice.vue'
import { formatCents } from '../composables/format'
import { usePayment } from '../composables/usePayment'
import locale from '../locales/pt-br'
import type { CardDetails } from '../types'

const props = defineProps<{ editionId: string, orderId: string, totalCents: number, editionName: string }>()
const emit = defineEmits<{ settled: [] }>()

const flow = usePayment(props.editionId, props.orderId)
const { receiver, receiverState, payment, busy, error, status, paid, failedMessage } = flow
const method = ref<'pix' | 'card'>('pix')
const checking = ref(false)

const methods = computed(() => receiver.value?.methods ?? [])
const showChoice = computed(() => !payment.value || status.value === 'failed' || status.value === 'cancelled')
const cardHint = computed(() => ((receiver.value?.maxInstallments ?? 1) > 1 ? locale.cardHint.replace('{n}', String(receiver.value?.maxInstallments)) : locale.cardHintOne))

watch(methods, (list) => {
  if (list.length && !list.includes(method.value)) method.value = list[0]!
}, { immediate: true })

watch(() => [paid.value, status.value, payment.value?.orderStatus], () => {
  if (paid.value || status.value === 'expired' || payment.value?.orderStatus === 'expired' || payment.value?.orderStatus === 'cancelled') emit('settled')
})

async function check() {
  checking.value = true
  await flow.refresh()
  checking.value = false
}

const payPix = () => flow.start('pix')
const payHosted = (installments: number) => flow.start('card', installments)
const payCard = (card: CardDetails) => flow.start('card', card.installments, card)

function again() {
  flow.retry()
}

onMounted(flow.load)
onBeforeUnmount(flow.stop)
</script>

<template>
  <section class="flex flex-col gap-4 rounded-box border border-base-300 bg-base-100 p-4" aria-labelledby="payment-title" data-testid="payment-panel">
    <h2 id="payment-title" class="text-lg font-semibold">{{ locale.paymentTitle }}</h2>

    <div v-if="receiverState === 'loading'" class="skeleton h-16 w-full" aria-hidden="true" />
    <div v-else-if="receiverState === 'error'" role="alert" class="alert alert-error alert-soft flex flex-wrap justify-between gap-3">
      <span>{{ locale.genericError }}</span>
      <button type="button" class="btn btn-sm" @click="flow.loadReceiver">{{ locale.retry }}</button>
    </div>
    <p v-else-if="!receiver || !receiver.ready || !methods.length" role="alert" class="alert alert-warning alert-soft">{{ locale.receiverMissing }}</p>

    <template v-else>
      <ReceiverNotice :receiver="receiver" :edition-name="editionName" />

      <div v-if="status === 'failed'" role="alert" class="alert alert-error alert-soft flex flex-col items-start gap-2" data-testid="payment-declined">
        <b>{{ locale.declinedTitle }}</b>
        <span class="text-sm">{{ failedMessage }}</span>
        <button type="button" class="btn btn-sm" @click="again">{{ locale.tryAgain }}</button>
      </div>

      <PixPayment v-else-if="payment && payment.method === 'pix' && status === 'pending'" :payment="payment" :checking="checking" @check="check" />

      <div v-else-if="payment && payment.method === 'card' && status === 'pending'" class="flex flex-col gap-3" role="status">
        <p v-if="payment.checkoutUrl" class="text-sm">{{ locale.hostedPending }}</p>
        <p v-else class="flex items-center gap-2 text-sm"><span class="loading loading-spinner loading-sm" aria-hidden="true" />{{ locale.processing }}</p>
        <a v-if="payment.checkoutUrl" :href="payment.checkoutUrl" class="btn btn-primary self-start">{{ locale.openHosted }}</a>
        <button type="button" class="btn btn-ghost btn-sm self-start" :disabled="checking" @click="check">{{ locale.checkNow }}</button>
      </div>

      <template v-if="showChoice && status !== 'failed'">
        <fieldset class="flex flex-col gap-2">
          <legend class="mb-1 text-sm font-medium">{{ locale.methodLabel }}</legend>
          <label v-if="methods.includes('pix')" class="flex min-h-11 cursor-pointer items-center gap-3 rounded-box border border-base-300 px-3" :class="{ 'border-(--ph-brand)': method === 'pix' }">
            <input v-model="method" type="radio" class="radio radio-sm" name="payment-method" value="pix">
            <span class="flex flex-col"><b class="font-medium">{{ locale.pix }}</b><span class="text-xs text-(--ph-ink-3)">{{ locale.pixHint }}</span></span>
          </label>
          <label v-if="methods.includes('card')" class="flex min-h-11 cursor-pointer items-center gap-3 rounded-box border border-base-300 px-3" :class="{ 'border-(--ph-brand)': method === 'card' }">
            <input v-model="method" type="radio" class="radio radio-sm" name="payment-method" value="card">
            <span class="flex flex-col"><b class="font-medium">{{ locale.card }}</b><span class="text-xs text-(--ph-ink-3)">{{ cardHint }}</span></span>
          </label>
        </fieldset>

        <p v-if="error" role="alert" class="text-sm text-error" data-testid="payment-error">{{ error }}</p>

        <button v-if="method === 'pix'" type="button" class="btn btn-primary w-full" :disabled="busy" :aria-busy="busy ? 'true' : undefined" @click="payPix">
          <span v-if="busy" class="loading loading-spinner loading-sm" aria-hidden="true" />
          {{ locale.payPix.replace('{valor}', formatCents(totalCents)) }}
        </button>
        <BrowserCardPayment v-else-if="receiver.cardInBrowser && receiver.publicKey" :public-key="receiver.publicKey" :total-cents="totalCents" :max-installments="receiver.maxInstallments" :busy="busy" @pay="payCard" />
        <HostedCardPayment v-else :total-cents="totalCents" :max-installments="receiver.maxInstallments" :busy="busy" @pay="payHosted" />
      </template>
    </template>
  </section>
</template>
