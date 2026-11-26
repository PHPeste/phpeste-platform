<script setup lang="ts">
import { ref } from 'vue'
import { formatCents, formatClock } from '../composables/format'
import locale from '../locales/pt-br'
import type { Payment } from '../types'

const props = defineProps<{ payment: Payment, checking: boolean }>()
defineEmits<{ check: [] }>()
const feedback = ref<string | null>(null)

async function copy() {
  try {
    await navigator.clipboard.writeText(props.payment.pix?.copyPaste ?? '')
    feedback.value = locale.copied
  } catch {
    feedback.value = locale.copyFailed
  }
}
</script>

<template>
  <div class="flex flex-col gap-3" data-testid="pix-payment">
    <h3 class="text-lg font-semibold">{{ locale.pixTitle }}</h3>
    <p class="text-sm text-(--ph-ink-2)">{{ locale.pixSteps }}</p>
    <img
      v-if="payment.pix?.qrImage"
      :src="`data:image/png;base64,${payment.pix.qrImage}`"
      :alt="locale.pixQr.replace('{valor}', formatCents(payment.amountCents))"
      class="size-56 self-center rounded-box border border-base-300 bg-white p-2"
    >
    <label for="pix-code" class="text-sm font-medium">{{ locale.pixCode }}</label>
    <textarea id="pix-code" class="textarea w-full font-mono text-xs break-all" rows="3" readonly :value="payment.pix?.copyPaste" @focus="($event.target as HTMLTextAreaElement).select()" />
    <button type="button" class="btn btn-primary w-full sm:w-auto" @click="copy">{{ locale.copy }}</button>
    <p class="min-h-5 text-sm" role="status" data-testid="pix-copy-status">{{ feedback }}</p>
    <p v-if="payment.expiresAt" class="text-sm">{{ locale.pixValidUntil.replace('{hora}', formatClock(payment.expiresAt)) }}</p>
    <p class="flex items-center gap-2 text-sm text-(--ph-ink-2)" role="status">
      <span class="loading loading-dots loading-sm" aria-hidden="true" />
      {{ locale.waiting }}
    </p>
    <button type="button" class="btn btn-ghost btn-sm self-start" :disabled="checking" @click="$emit('check')">{{ locale.checkNow }}</button>
  </div>
</template>
