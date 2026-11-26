<script setup lang="ts">
import { formatCents } from '../composables/format'
import locale from '../locales/pt-br'
import type { Quote } from '../types'

defineProps<{ quote: Quote | null, error: string | null, busy: boolean }>()
const code = defineModel<string>({ default: '' })
defineEmits<{ apply: [], remove: [] }>()
</script>

<template>
  <form class="flex flex-col gap-1.5" novalidate @submit.prevent="$emit('apply')">
    <label for="checkout-coupon" class="text-sm font-medium">{{ locale.coupon }}</label>
    <div class="flex gap-2">
      <input
        id="checkout-coupon"
        v-model="code"
        class="input w-full uppercase"
        :class="{ 'input-error': error }"
        autocomplete="off"
        :readonly="!!quote"
        :aria-invalid="error ? 'true' : undefined"
        aria-describedby="checkout-coupon-status"
      >
      <button v-if="!quote" type="submit" class="btn btn-secondary" :disabled="busy">{{ locale.couponApply }}</button>
      <button v-else type="button" class="btn btn-ghost" @click="$emit('remove')">{{ locale.couponRemove }}</button>
    </div>
    <p id="checkout-coupon-status" role="status" class="text-xs" :class="error ? 'text-error' : 'text-success'">
      <template v-if="error">{{ error }}</template>
      <template v-else-if="quote">{{ locale.couponApplied.replace('{codigo}', quote.code).replace('{valor}', formatCents(quote.discountCents)) }}</template>
    </p>
  </form>
</template>
