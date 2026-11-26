<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { formatCents, formatClock, formatCountdown } from '../composables/format'
import { useOrder } from '../composables/useOrder'
import locale from '../locales/pt-br'

const props = defineProps<{ editionId: string, orderId: string }>()
const emit = defineEmits<{ lapsed: [reason: 'expired' | 'cancelled'] }>()

const { order, state, busy, holding, secondsLeft, lapsed, load, cancel, stop } = useOrder(props.editionId, props.orderId)
const announcement = ref('')
const confirming = ref(false)
const cancelError = ref<string | null>(null)

const minutesLeft = computed(() => (secondsLeft.value === null ? null : Math.ceil(secondsLeft.value / 60)))

watch(minutesLeft, (minutes, previous) => {
  if (minutes === null || previous === null || previous === undefined || minutes === previous) return
  if (minutes === 5) announcement.value = locale.minutesLeft.replace('{n}', '5')
  if (minutes === 1) announcement.value = locale.oneMinuteLeft
})

watch(lapsed, (reason) => {
  if (!reason) return
  stop()
  emit('lapsed', reason)
})

async function giveUp() {
  cancelError.value = await cancel()
  confirming.value = false
}

onMounted(load)
onBeforeUnmount(stop)
</script>

<template>
  <section class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div v-if="state === 'loading'" class="flex flex-col gap-3" role="status" aria-busy="true">
      <span class="sr-only">{{ locale.orderLoading }}</span>
      <div class="skeleton h-8 w-1/3" />
      <div class="skeleton h-40 w-full" />
    </div>

    <div v-else-if="state === 'not_found'" class="flex flex-col items-start gap-3">
      <p role="alert" class="alert alert-info alert-soft">{{ locale.orderNotFound }}</p>
      <NuxtLink to="/ingressos" class="btn btn-primary">{{ locale.startOver }}</NuxtLink>
    </div>

    <div v-else-if="state === 'error'" role="alert" class="alert alert-error alert-soft flex flex-wrap justify-between gap-3">
      <span>{{ locale.genericError }}</span>
      <button type="button" class="btn btn-sm" @click="load">{{ locale.retry }}</button>
    </div>

    <template v-else-if="order">
      <header class="flex flex-col gap-2">
        <h1 class="text-3xl leading-tight font-semibold">{{ order.status === 'paid' ? locale.paid : locale.awaiting }}</h1>
        <p class="text-(--ph-ink-2)">{{ locale.orderTitle.replace('{numero}', String(order.number)) }} · {{ locale.buyerLine.replace('{nome}', order.buyer.name).replace('{email}', order.buyer.email) }}</p>
        <p class="text-(--ph-ink-2)">{{ order.status === 'paid' ? locale.paidText : locale.awaitingText }}</p>
      </header>

      <div v-if="holding && secondsLeft !== null && order.expiresAt" class="flex flex-wrap items-center justify-between gap-3 rounded-box border border-(--ph-accent) bg-base-100 p-4" data-testid="reservation-timer">
        <div class="flex flex-col">
          <span class="text-sm text-(--ph-ink-2)">{{ locale.reservedUntil.replace('{hora}', formatClock(order.expiresAt)) }}</span>
          <span class="text-xs text-(--ph-ink-3)">{{ locale.timeLeft }}</span>
        </div>
        <span role="timer" class="text-3xl font-semibold tabular-nums" :aria-label="locale.timeLeftLabel.replace('{tempo}', formatCountdown(secondsLeft))">{{ formatCountdown(secondsLeft) }}</span>
      </div>
      <p class="sr-only" aria-live="polite" data-testid="timer-announcer">{{ announcement }}</p>

      <section class="flex flex-col gap-3 rounded-box border border-base-300 bg-base-100 p-4" aria-labelledby="order-items-title">
        <h2 id="order-items-title" class="text-lg font-semibold">{{ locale.itemsTitle }}</h2>
        <ul class="flex flex-col divide-y divide-base-300">
          <li v-for="item in order.items" :key="item.id" class="flex flex-wrap justify-between gap-2 py-2 text-sm">
            <span class="min-w-0">
              <b class="block font-medium">{{ item.ticketTypeName }}<template v-if="item.batchName"> · {{ item.batchName }}</template></b>
              <span class="text-(--ph-ink-2)">{{ item.attendee.name }} · {{ item.attendee.email }}</span>
            </span>
            <span class="tabular-nums">{{ formatCents(item.unitPriceCents) }}</span>
          </li>
        </ul>
        <dl class="flex flex-col gap-2 border-t border-base-300 pt-2 text-sm" data-testid="order-totals">
          <div class="flex justify-between gap-3"><dt>{{ locale.subtotal }}</dt><dd class="tabular-nums">{{ formatCents(order.subtotalCents) }}</dd></div>
          <div v-if="order.discountCents > 0" class="flex justify-between gap-3 text-success"><dt>{{ locale.discount.replace('{codigo}', order.couponCode ?? '') }}</dt><dd class="tabular-nums">−{{ formatCents(order.discountCents) }}</dd></div>
          <div v-if="order.feeCents > 0" class="flex justify-between gap-3"><dt>{{ locale.fee }}</dt><dd class="tabular-nums">{{ formatCents(order.feeCents) }}</dd></div>
          <div class="flex justify-between gap-3 text-base font-semibold"><dt>{{ locale.total }}</dt><dd class="tabular-nums">{{ formatCents(order.totalCents) }}</dd></div>
        </dl>
      </section>

      <div v-if="holding" class="flex flex-col items-start gap-2">
        <p v-if="cancelError" role="alert" class="text-sm text-error">{{ cancelError }}</p>
        <div v-if="confirming" role="group" class="alert alert-warning alert-soft flex flex-wrap items-center gap-3" :aria-label="locale.cancelConfirm">
          <span>{{ locale.cancelConfirm }}</span>
          <button type="button" class="btn btn-sm btn-error" :disabled="busy" @click="giveUp">{{ locale.cancel }}</button>
          <button type="button" class="btn btn-sm btn-ghost" @click="confirming = false">{{ locale.keep }}</button>
        </div>
        <button v-else type="button" class="btn btn-ghost btn-sm" @click="confirming = true">{{ locale.cancel }}</button>
      </div>
    </template>
  </section>
</template>
