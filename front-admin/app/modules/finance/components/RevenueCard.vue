<script setup lang="ts">
import { computed } from 'vue'
import { formatCents } from '../composables/format'
import locale from '../locales/pt-br'
import type { Summary } from '../types'

const props = defineProps<{ summary: Summary }>()
const estimated = computed(() => props.summary.accounts.some((account) => account.estimatedFees))
</script>

<template>
  <section class="card border border-base-300 bg-base-100 p-5" aria-labelledby="revenue-title" data-testid="finance-revenue">
    <h2 id="revenue-title" class="mb-4 text-lg font-semibold">{{ locale.revenueTitle }}</h2>
    <dl class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-2 tabular-nums">
      <dt>{{ locale.revenueTickets }}</dt><dd class="text-right">{{ formatCents(summary.ticketsGrossCents) }}</dd>
      <template v-if="summary.ticketsRefundedCents > 0">
        <dt class="text-(--ph-ink-2)">{{ locale.revenueRefunds }}</dt><dd class="text-right text-(--ph-ink-2)">− {{ formatCents(summary.ticketsRefundedCents) }}</dd>
      </template>
      <dt class="text-(--ph-ink-2)">{{ estimated ? locale.revenueFeesEstimated : locale.revenueFees }}</dt>
      <dd class="text-right text-(--ph-ink-2)">− {{ formatCents(summary.ticketsFeesCents) }}</dd>
      <dt>{{ locale.revenueSponsors }}</dt><dd class="text-right">{{ formatCents(summary.sponsorsReceivedCents) }}</dd>
      <dt class="border-t border-(--ph-line) pt-2 font-semibold">{{ locale.revenueTotal }}</dt>
      <dd class="border-t border-(--ph-line) pt-2 text-right font-semibold">{{ formatCents(summary.revenueCents) }}</dd>
      <template v-if="summary.sponsorsOutstandingCents > 0">
        <dt class="text-xs text-(--ph-ink-3)">{{ locale.revenueSponsorsOutstanding }}</dt><dd class="text-right text-xs text-(--ph-ink-3)">{{ formatCents(summary.sponsorsOutstandingCents) }}</dd>
      </template>
    </dl>
    <p class="mt-3 text-xs text-(--ph-ink-3)">{{ locale.revenueHint }}</p>

    <template v-if="summary.accounts.length">
      <h3 class="mt-5 mb-2 text-xs font-semibold tracking-wide text-(--ph-ink-3) uppercase">{{ locale.byAccount }}</h3>
      <ul class="flex flex-col divide-y divide-(--ph-line) rounded-(--radius-box) border border-(--ph-line)">
        <li v-for="(account, index) in summary.accounts" :key="index" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm">
          <div class="min-w-0">
            <p class="font-medium">{{ account.accountLabel }}<span v-if="account.retired" class="font-normal text-(--ph-ink-3)"> · {{ locale.retired }}</span></p>
            <p class="text-xs text-(--ph-ink-3)">{{ account.orders === 1 ? locale.ordersOne : locale.orders.replace('{n}', String(account.orders)) }} · {{ formatCents(account.grossCents) }} − {{ formatCents(account.feesCents) }}</p>
          </div>
          <span class="tabular-nums">{{ locale.accountNet.replace('{valor}', formatCents(account.netCents)) }}</span>
        </li>
      </ul>
    </template>
    <p v-else class="mt-4 text-sm text-(--ph-ink-3)">{{ locale.noRevenue }}</p>
  </section>
</template>
