<script setup lang="ts">
import { formatCents, formatDate } from '../composables/format'
import locale from '../locales/pt-br'
import type { AccountOrder } from '../types'

defineProps<{ order: AccountOrder }>()

const tone = (status: string) => ({ paid: 'badge-success', refunded: 'badge-info', created: 'badge-warning', awaiting_payment: 'badge-warning' } as Record<string, string>)[status] ?? 'badge-ghost'
</script>

<template>
  <article class="card flex flex-col gap-4 border border-(--ph-line) bg-(--ph-surface) p-5" :aria-labelledby="`order-${order.id}`" data-testid="account-order">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="flex flex-col gap-1">
        <h2 :id="`order-${order.id}`" class="text-lg font-semibold">{{ locale.orderNumber.replace('{n}', String(order.number)) }}</h2>
        <span class="text-sm text-(--ph-ink-2)">
          <template v-if="order.createdAt">{{ locale.orderPlaced.replace('{data}', formatDate(order.createdAt)) }}</template>
          <template v-if="order.paidAt"> · {{ locale.orderPaid.replace('{data}', formatDate(order.paidAt)) }}</template>
        </span>
      </div>
      <span class="badge badge-soft" :class="tone(order.status)">{{ order.statusLabel }}</span>
    </div>
    <ul class="flex flex-col divide-y divide-(--ph-line) text-sm">
      <li v-for="item in order.items" :key="item.id" class="flex flex-wrap justify-between gap-2 py-2">
        <span>{{ item.attendeeName }} · {{ item.typeName }}<template v-if="item.batchName"> ({{ item.batchName }})</template><span v-if="item.cancelled" class="badge badge-soft badge-ghost ms-2">{{ locale.itemCancelled }}</span></span>
        <span class="tabular-nums">{{ formatCents(item.totalCents) }}</span>
      </li>
    </ul>
    <dl class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 text-sm">
      <template v-if="order.discountCents">
        <dt>{{ order.couponCode ? locale.coupon.replace('{cupom}', order.couponCode) : locale.discount }}</dt>
        <dd class="text-right tabular-nums">−{{ formatCents(order.discountCents) }}</dd>
      </template>
      <template v-if="order.feeCents">
        <dt>{{ locale.fee }}</dt>
        <dd class="text-right tabular-nums">{{ formatCents(order.feeCents) }}</dd>
      </template>
      <dt class="font-semibold">{{ locale.total }}</dt>
      <dd class="text-right font-semibold tabular-nums">{{ formatCents(order.totalCents) }}</dd>
      <template v-if="order.refundedCents">
        <dt>{{ locale.refunded }}</dt>
        <dd class="text-right tabular-nums">{{ formatCents(order.refundedCents) }}</dd>
      </template>
    </dl>
    <section v-if="order.refunds.length" class="flex flex-col gap-2" :aria-labelledby="`refunds-${order.id}`">
      <h3 :id="`refunds-${order.id}`" class="font-semibold">{{ locale.refundsTitle }}</h3>
      <ul class="flex flex-col gap-2 text-sm">
        <li v-for="refund in order.refunds" :key="refund.id" class="flex flex-wrap items-center gap-2">
          <span>{{ locale.refundLine.replace('{valor}', formatCents(refund.amountCents)).replace('{motivo}', refund.reasonLabel) }}</span>
          <span class="badge badge-soft" :class="refund.status === 'completed' ? 'badge-success' : refund.status === 'failed' ? 'badge-error' : 'badge-warning'">{{ refund.statusLabel }}</span>
          <span class="text-(--ph-ink-3)">
            <template v-if="refund.completedAt">{{ locale.refundCompleted.replace('{data}', formatDate(refund.completedAt)) }}</template>
            <template v-else-if="refund.createdAt">{{ locale.refundRequested.replace('{data}', formatDate(refund.createdAt)) }}</template>
          </span>
        </li>
      </ul>
    </section>
    <NuxtLink v-if="order.pending" :to="`/checkout/pedido/${order.id}`" class="btn btn-primary btn-sm self-start">{{ locale.continuePayment }}</NuxtLink>
  </article>
</template>
