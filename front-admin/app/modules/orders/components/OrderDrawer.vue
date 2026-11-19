<script setup lang="ts">
import { computed, ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import MarkPaidModal from './MarkPaidModal.vue'
import RefundModal from './RefundModal.vue'
import { ORDER_TONE, REFUND_TONE } from './statusTone'
import { refundableCents } from '../composables/useOrders'
import { formatCents, formatDateTime } from '../composables/format'
import locale from '../locales/pt-br'
import type { MutationResult, OrderDetail, Refund, RefundInput, RefundResult } from '../types'

const props = defineProps<{
  detail: OrderDetail | null
  state: 'idle' | 'loading' | 'ready' | 'error'
  disabled: boolean
  canManage: boolean
  canRefund: boolean
  cancel: () => Promise<MutationResult>
  markPaid: (justification: string) => Promise<MutationResult>
  remind: () => Promise<MutationResult>
  refund: (input: RefundInput) => Promise<RefundResult>
  settle: (refund: Refund, completed: boolean) => Promise<MutationResult>
}>()
const open = defineModel<boolean>({ default: false })
defineEmits<{ retry: [] }>()

const toast = useToast()
const busy = ref<string | null>(null)
const cancelOpen = ref(false)
const markPaidOpen = ref(false)
const refundOpen = ref(false)

const order = computed(() => props.detail?.order ?? null)
const pending = computed(() => order.value !== null && ['created', 'awaiting_payment'].includes(order.value.status))
const external = computed(() => order.value?.externalPlatform ?? null)

const can = computed(() => {
  const o = order.value
  if (!o || props.disabled) return { remind: false, markPaid: false, cancel: false, refund: false }
  const reservationValid = o.expiresAt !== null && new Date(o.expiresAt).getTime() > Date.now()
  return {
    remind: props.canManage && o.status === 'awaiting_payment' && reservationValid,
    markPaid: props.canManage && pending.value && o.totalCents > 0,
    cancel: props.canManage && pending.value,
    refund: props.canRefund && external.value === null && o.totalCents > 0 && ['paid', 'refunded'].includes(o.status) && refundableCents(o) > 0,
  }
})
const hasActions = computed(() => Object.values(can.value).some(Boolean))

async function run(key: string, action: () => Promise<MutationResult>, success: string) {
  busy.value = key
  const result = await action()
  busy.value = null
  if (result.ok) toast.success(success)
  else toast.error(result.message)
  return result.ok
}

const remindNow = () => run('remind', props.remind, locale.reminded.replace('{email}', order.value?.buyerEmail ?? ''))

async function confirmCancel() {
  if (await run('cancel', props.cancel, locale.cancelled)) cancelOpen.value = false
}

const settleRefund = (refund: Refund, completed: boolean) => run(`settle-${refund.id}`, () => props.settle(refund, completed), locale.settled)
</script>

<template>
  <PhDrawer v-model="open" :title="order ? locale.orderTitle.replace('{n}', String(order.number)) : locale.title">
    <PhPageSkeleton v-if="state === 'loading' && !detail" :cards="1" :lines="6" />
    <PhLoadError v-else-if="state === 'error'" :title="locale.detailError" @retry="$emit('retry')" />

    <div v-else-if="detail && order" class="flex flex-col gap-6" data-testid="order-detail">
      <div class="flex flex-wrap items-center gap-2">
        <PhBadge :status="ORDER_TONE[order.status]">{{ order.statusLabel }}</PhBadge>
        <span class="text-xs text-(--ph-ink-3)">{{ formatDateTime(order.createdAt) }}</span>
      </div>

      <section class="flex flex-col gap-1" :aria-label="locale.buyer">
        <h3 class="text-xs font-semibold tracking-wide text-(--ph-ink-3) uppercase">{{ locale.buyer }}</h3>
        <p class="font-medium">{{ order.buyerName }}</p>
        <p class="text-(--ph-ink-2)">{{ order.buyerEmail }}</p>
        <p v-if="order.buyerDocument" class="text-(--ph-ink-3) tabular-nums">{{ locale.document }} {{ order.buyerDocument }}</p>
      </section>

      <section class="flex flex-col gap-2">
        <h3 class="text-xs font-semibold tracking-wide text-(--ph-ink-3) uppercase">{{ locale.items }}</h3>
        <ul class="flex flex-col divide-y divide-(--ph-line) rounded-(--radius-box) border border-(--ph-line)">
          <li v-for="item in order.items" :key="item.id" class="flex items-start justify-between gap-3 px-3 py-2" :class="{ 'opacity-60': item.cancelled }">
            <div>
              <p class="font-medium">{{ item.attendeeName }}</p>
              <p class="text-xs text-(--ph-ink-3)">{{ item.ticketTypeName }}<template v-if="item.batchName"> · {{ item.batchName }}</template><template v-if="item.externalCode"> · {{ item.externalCode }}</template></p>
            </div>
            <div class="text-right">
              <p class="tabular-nums">{{ formatCents(item.totalCents) }}</p>
              <PhBadge v-if="item.cancelled" status="neutral" :dot="false">{{ locale.cancelledItem }}</PhBadge>
            </div>
          </li>
        </ul>
      </section>

      <section class="flex flex-col gap-2">
        <h3 class="text-xs font-semibold tracking-wide text-(--ph-ink-3) uppercase">{{ locale.payment }}</h3>
        <p v-if="external" class="text-(--ph-ink-2)">{{ locale.importedPayment.replace('{plataforma}', external) }}</p>
        <p v-else-if="order.totalCents === 0" class="text-(--ph-ink-2)">{{ locale.free }}</p>
        <div v-else-if="detail.payment" class="flex flex-wrap items-center gap-2" data-testid="order-payment">
          <span class="font-medium">{{ detail.payment.methodLabel }}<template v-if="detail.payment.installments > 1"> · {{ locale.installments.replace('{n}', String(detail.payment.installments)) }}</template></span>
          <PhBadge :status="detail.payment.status === 'paid' ? 'ok' : detail.payment.status === 'pending' ? 'warn' : 'neutral'">{{ detail.payment.statusLabel }}</PhBadge>
          <span v-if="detail.payment.failureReason" class="w-full text-xs text-(--ph-danger)">{{ detail.payment.failureReason }}</span>
        </div>
        <p v-else class="text-(--ph-ink-3)">{{ locale.noPayment }}</p>
        <p v-if="pending && order.expiresAt" class="text-xs text-(--ph-ink-3)">{{ locale.expiresAt.replace('{data}', formatDateTime(order.expiresAt)) }}</p>

        <dl class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 tabular-nums">
          <dt class="text-(--ph-ink-2)">{{ locale.subtotal }}</dt><dd class="text-right">{{ formatCents(order.subtotalCents) }}</dd>
          <template v-if="order.discountCents > 0">
            <dt class="text-(--ph-ink-2)">{{ locale.discount }}<template v-if="order.couponCode"> ({{ locale.coupon.replace('{codigo}', order.couponCode) }})</template></dt>
            <dd class="text-right">−{{ formatCents(order.discountCents) }}</dd>
          </template>
          <template v-if="order.feeCents > 0">
            <dt class="text-(--ph-ink-2)">{{ locale.fee }}</dt><dd class="text-right">{{ formatCents(order.feeCents) }}</dd>
          </template>
          <dt class="font-semibold">{{ locale.orderTotal }}</dt><dd class="text-right font-semibold" data-testid="order-total">{{ formatCents(order.totalCents) }}</dd>
          <template v-if="order.refundedCents > 0">
            <dt class="text-(--ph-ink-2)">{{ locale.refunded }}</dt><dd class="text-right">−{{ formatCents(order.refundedCents) }}</dd>
          </template>
          <template v-if="order.gatewayFeeCents > 0">
            <dt class="text-xs text-(--ph-ink-3)">{{ locale.gatewayFee }}</dt><dd class="text-right text-xs text-(--ph-ink-3)">{{ formatCents(order.gatewayFeeCents) }}</dd>
          </template>
        </dl>
      </section>

      <section v-if="detail.refunds.length" class="flex flex-col gap-2">
        <h3 class="text-xs font-semibold tracking-wide text-(--ph-ink-3) uppercase">{{ locale.refunds }}</h3>
        <ul class="flex flex-col gap-2">
          <li v-for="item in detail.refunds" :key="item.id" class="flex flex-col gap-1 rounded-(--radius-box) border border-(--ph-line) px-3 py-2" data-testid="order-refund">
            <div class="flex items-center justify-between gap-2">
              <span class="font-medium tabular-nums">{{ formatCents(item.amountCents) }}</span>
              <PhBadge :status="REFUND_TONE[item.status]">{{ item.statusLabel }}</PhBadge>
            </div>
            <p class="text-xs text-(--ph-ink-3)">{{ item.reasonLabel }} · {{ formatDateTime(item.createdAt) }}<template v-if="item.note"> · {{ item.note }}</template></p>
            <p v-if="item.failureReason" class="text-xs text-(--ph-danger)">{{ item.failureReason }}</p>
            <template v-if="item.status === 'unconfirmed' && canRefund && !disabled">
              <p class="text-xs text-(--ph-ink-2)">{{ locale.unconfirmedHint }}</p>
              <div class="flex flex-wrap gap-2">
                <PhButton variant="secondary" :loading="busy === `settle-${item.id}`" @click="settleRefund(item, true)">{{ locale.settleDone }}</PhButton>
                <PhButton variant="ghost" :loading="busy === `settle-${item.id}`" @click="settleRefund(item, false)">{{ locale.settleFailed }}</PhButton>
              </div>
            </template>
          </li>
        </ul>
      </section>

      <section class="flex flex-col gap-2">
        <h3 class="text-xs font-semibold tracking-wide text-(--ph-ink-3) uppercase">{{ locale.history }}</h3>
        <ol class="ml-1.5 flex flex-col gap-3 border-l border-(--ph-line) pl-4" data-testid="order-history">
          <li v-for="entry in detail.history" :key="entry.id" class="relative">
            <span class="absolute top-1.5 -left-[1.3rem] size-2 rounded-full bg-(--ph-brand)" aria-hidden="true" />
            <p class="font-medium">{{ entry.title }}</p>
            <p v-if="entry.detail" class="text-(--ph-ink-2)">{{ entry.detail }}</p>
            <p class="text-xs text-(--ph-ink-3)">{{ formatDateTime(entry.at) }} · {{ entry.actorName === null ? locale.system : locale.by.replace('{nome}', entry.actorName || '—') }}</p>
          </li>
        </ol>
      </section>
    </div>

    <template v-if="detail && order && hasActions" #footer>
      <div class="flex w-full flex-wrap justify-end gap-2" role="group" :aria-label="locale.actions">
        <PhButton v-if="can.cancel" variant="ghost" class="text-error" @click="cancelOpen = true">{{ locale.cancel }}</PhButton>
        <PhButton v-if="can.remind" variant="secondary" :loading="busy === 'remind'" @click="remindNow">{{ locale.remind }}</PhButton>
        <PhButton v-if="can.markPaid" variant="secondary" @click="markPaidOpen = true">{{ locale.markPaid }}</PhButton>
        <PhButton v-if="can.refund" @click="refundOpen = true">{{ locale.refund }}</PhButton>
      </div>
    </template>
  </PhDrawer>

  <template v-if="order">
    <PhModal v-model="cancelOpen" :title="locale.cancelTitle.replace('{n}', String(order.number))">
      <p>{{ locale.cancelText }}</p>
      <template #footer>
        <PhButton variant="ghost" @click="cancelOpen = false">{{ locale.back }}</PhButton>
        <PhButton variant="danger" :loading="busy === 'cancel'" @click="confirmCancel">{{ locale.cancelConfirm }}</PhButton>
      </template>
    </PhModal>
    <MarkPaidModal v-model="markPaidOpen" :order="order" :mark-paid="markPaid" @saved="toast.success(locale.markedPaid)" />
    <RefundModal v-model="refundOpen" :order="order" :refund="refund" />
  </template>
</template>
