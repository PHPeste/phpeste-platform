<script setup lang="ts">
import TicketQr from '@/components/TicketQr.vue'
import locale from '../locales/pt-br'
import type { AccountTicket } from '../types'

defineProps<{ ticket: AccountTicket }>()
defineEmits<{ save: [ticket: AccountTicket] }>()

const missingText = (fields: string[]) => fields.map((field) => locale.missingFields[field] ?? field).join(', ')
</script>

<template>
  <article class="card flex flex-col gap-4 border border-(--ph-line) bg-(--ph-surface) p-5 break-inside-avoid sm:flex-row sm:items-center" :aria-labelledby="`ticket-${ticket.id}`" data-testid="account-ticket">
    <TicketQr v-if="ticket.qr" :value="ticket.qr" :label="locale.ticketQr.replace('{nome}', ticket.holderName)" class="self-center" />
    <div class="flex min-w-0 flex-1 flex-col gap-1">
      <span class="text-xs text-(--ph-ink-3)">{{ ticket.typeName }}<template v-if="ticket.orderNumber"> · {{ locale.orderNumber.replace('{n}', String(ticket.orderNumber)) }}</template></span>
      <h2 :id="`ticket-${ticket.id}`" class="text-lg font-semibold">{{ ticket.holderName }}</h2>
      <span class="font-mono text-sm text-(--ph-ink-2)">{{ locale.ticketCode.replace('{codigo}', ticket.code) }}</span>
      <p v-if="!ticket.active" class="text-sm text-error">{{ locale.cancelled }}</p>
      <p v-else-if="ticket.missing.length" class="badge badge-soft badge-warning h-auto py-1 print:hidden">{{ locale.missing }}: {{ missingText(ticket.missing) }}</p>
      <div v-if="ticket.active" class="mt-2 flex flex-wrap gap-2 print:hidden">
        <NuxtLink :to="`/conta/ingressos/${ticket.id}`" class="btn btn-primary btn-sm" :aria-label="`${locale.openTicket}: ${ticket.holderName}`">{{ locale.openTicket }}</NuxtLink>
        <button type="button" class="btn btn-ghost btn-sm" :aria-label="`${locale.saveImage}: ${ticket.holderName}`" @click="$emit('save', ticket)">{{ locale.saveImage }}</button>
      </div>
    </div>
  </article>
</template>
