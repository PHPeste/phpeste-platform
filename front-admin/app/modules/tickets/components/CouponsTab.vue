<script setup lang="ts">
import { ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import CouponModal from './CouponModal.vue'
import { formatCents, formatDateTime } from '../composables/money'
import locale from '../locales/pt-br'
import type { Coupon, CouponInput, MutationResult, TicketType } from '../types'

defineProps<{
  coupons: Coupon[]
  types: TicketType[]
  disabled: boolean
  save: (coupon: Coupon | null, input: CouponInput) => Promise<MutationResult>
  remove: (coupon: Coupon) => Promise<MutationResult>
}>()

const toast = useToast()
const open = ref(false)
const editing = ref<Coupon | null>(null)
const TONE: Record<string, 'ok' | 'warn' | 'neutral' | 'info'> = { active: 'ok', paused: 'neutral', scheduled: 'info', expired: 'neutral', exhausted: 'warn' }

function edit(coupon: Coupon | null) {
  editing.value = coupon
  open.value = true
}

const discount = (coupon: Coupon) => (coupon.kind === 'percent' ? `${coupon.value}%` : formatCents(coupon.value))

function validity(coupon: Coupon): string {
  const from = formatDateTime(coupon.startsAt)
  const to = formatDateTime(coupon.endsAt)
  if (from && to) return locale.fromTo.replace('{from}', from).replace('{to}', to)
  if (to) return locale.until.replace('{to}', to)
  if (from) return locale.from.replace('{from}', from)
  return locale.anytime
}
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-3">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">{{ locale.tabs.coupons }}</h2>
        <PhButton v-if="!disabled && coupons.length" @click="edit(null)">{{ locale.newCoupon }}</PhButton>
      </div>
      <PhEmptyState v-if="!coupons.length" :title="locale.noCoupons" :description="locale.noCouponsHint">
        <PhButton v-if="!disabled" @click="edit(null)">{{ locale.newCoupon }}</PhButton>
      </PhEmptyState>
      <PhTable v-else :caption="locale.tabs.coupons">
        <thead>
          <tr>
            <th scope="col">{{ locale.code }}</th>
            <th scope="col">{{ locale.discount }}</th>
            <th scope="col" class="text-right">{{ locale.uses }}</th>
            <th scope="col">{{ locale.validity }}</th>
            <th scope="col" class="text-right">{{ locale.discountGiven }}</th>
            <th scope="col">{{ locale.status }}</th>
            <th scope="col"><span class="sr-only">{{ locale.actions }}</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="coupon in coupons" :key="coupon.id">
            <td>
              <code class="font-medium">{{ coupon.code }}</code>
              <span v-if="coupon.description" class="block text-xs text-(--ph-ink-3)">{{ coupon.description }}</span>
            </td>
            <td>{{ discount(coupon) }}</td>
            <td class="text-right tabular-nums">{{ coupon.maxUses === null ? locale.usesUnlimited.replace('{used}', String(coupon.used)) : locale.usesOf.replace('{used}', String(coupon.used)).replace('{max}', String(coupon.maxUses)) }}</td>
            <td class="text-sm">{{ validity(coupon) }}</td>
            <td class="text-right tabular-nums">{{ formatCents(coupon.discountTotalCents) }}</td>
            <td><PhBadge :status="TONE[coupon.status] ?? 'neutral'">{{ locale.couponStatus[coupon.status] ?? coupon.status }}</PhBadge></td>
            <td class="text-right">
              <PhButton v-if="!disabled" variant="ghost" :aria-label="`${locale.edit} ${coupon.code}`" @click="edit(coupon)">{{ locale.edit }}</PhButton>
            </td>
          </tr>
        </tbody>
      </PhTable>
    </div>
    <CouponModal
      v-model="open"
      :coupon="editing"
      :types="types"
      :save="save"
      :remove="remove"
      @saved="toast.success(locale.couponSaved.replace('{codigo}', $event))"
      @removed="toast.success(locale.couponRemoved.replace('{codigo}', $event))"
    />
  </PhCard>
</template>
