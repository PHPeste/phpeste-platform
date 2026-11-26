<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue'
import CouponField from './CouponField.vue'
import DataStep from './DataStep.vue'
import OrderSummary from './OrderSummary.vue'
import TicketPicker from './TicketPicker.vue'
import { useCheckout } from '../composables/useCheckout'
import locale from '../locales/pt-br'

const props = defineProps<{ editionId: string, notice: 'expired' | 'cancelled' | null }>()
const emit = defineEmits<{ reserved: [orderId: string] }>()

const checkout = useCheckout(props.editionId)
const { tickets, soldOut, state, step, quantities, couponInput, quote, couponError, couponBusy, buyer, attendees, errors, message, busy, lines, subtotalCents, discountCents, totalCents, feePending, requiresPayment, minErrors } = checkout
const heading = ref<HTMLElement | null>(null)
const stepLabel = () => locale.stepOf.replace('{n}', String(step.value)).replace('{nome}', locale.steps[step.value] ?? '')

async function focusHeading() {
  await nextTick()
  heading.value?.focus()
}

async function next() {
  if (checkout.next()) await focusHeading()
}

async function back() {
  checkout.back()
  await focusHeading()
}

async function reserve() {
  const orderId = await checkout.reserve()
  if (orderId) emit('reserved', orderId)
}

onMounted(() => checkout.load(props.notice === 'expired'))
</script>

<template>
  <section class="mx-auto flex w-full max-w-5xl flex-col gap-6">
    <header class="flex flex-col gap-2">
      <h1 ref="heading" tabindex="-1" class="text-3xl leading-tight font-semibold outline-none">{{ locale.title }}</h1>
      <p class="text-(--ph-ink-2)">{{ locale.intro }}</p>
      <p class="text-sm font-medium text-(--ph-ink-2)" data-testid="checkout-step">{{ stepLabel() }}</p>
    </header>

    <p v-if="notice && step === 1" role="alert" class="alert alert-warning alert-soft" data-testid="checkout-notice">{{ notice === 'expired' ? locale.expired : locale.cancelled }}</p>

    <div v-if="state === 'loading'" class="flex flex-col gap-3" role="status" aria-busy="true">
      <div class="skeleton h-24 w-full" />
      <div class="skeleton h-24 w-full" />
    </div>
    <div v-else-if="state === 'error'" role="alert" class="alert alert-error alert-soft flex flex-wrap justify-between gap-3">
      <span>{{ locale.loadError }}</span>
      <button type="button" class="btn btn-sm" @click="checkout.load()">{{ locale.retry }}</button>
    </div>
    <p v-else-if="soldOut" role="status" class="alert alert-info alert-soft">{{ locale.soldOut }}</p>
    <p v-else-if="!tickets.length" role="status" class="alert alert-info alert-soft">{{ locale.noTickets }}</p>

    <div v-else class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
      <div class="flex min-w-0 flex-col gap-4">
        <template v-if="step === 1">
          <TicketPicker :tickets="tickets" :quantities="quantities" :options="checkout.quantityOptions" :errors="minErrors" @change="checkout.setQuantity" />
        </template>
        <DataStep v-else v-model:buyer="buyer" :lines="lines" :attendees="attendees" :errors="errors" :requires-payment="requiresPayment" @copy="checkout.copyBuyer" />
      </div>

      <aside class="flex flex-col gap-4 lg:sticky lg:top-4">
        <OrderSummary :lines="lines" :subtotal-cents="subtotalCents" :discount-cents="discountCents" :coupon-code="quote?.code ?? null" :total-cents="totalCents" :fee-pending="feePending">
          <CouponField v-if="step === 1" v-model="couponInput" :quote="quote" :error="couponError" :busy="couponBusy" @apply="checkout.applyCoupon" @remove="checkout.removeCoupon" />
          <p v-if="message" role="alert" class="text-sm text-error" data-testid="checkout-message">{{ message }}</p>
          <template v-if="step === 1">
            <button type="button" class="btn btn-primary w-full" @click="next">{{ locale.continue }}</button>
          </template>
          <template v-else>
            <p class="text-xs text-(--ph-ink-3)">{{ locale.reserveHint }}</p>
            <button type="button" class="btn btn-primary w-full" :disabled="busy" @click="reserve">
              <span v-if="busy" class="loading loading-spinner loading-sm" aria-hidden="true" />
              {{ busy ? locale.reserving : requiresPayment ? locale.reserve : locale.reserveFree }}
            </button>
            <button type="button" class="btn btn-ghost w-full" :disabled="busy" @click="back">{{ locale.back }}</button>
          </template>
        </OrderSummary>
      </aside>
    </div>
  </section>
</template>
