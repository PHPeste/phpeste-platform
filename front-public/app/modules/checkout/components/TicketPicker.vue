<script setup lang="ts">
import { formatCents, formatDateTime } from '../composables/format'
import locale from '../locales/pt-br'
import type { TicketOption } from '../types'

defineProps<{ tickets: TicketOption[], quantities: Record<string, number>, options: (ticket: TicketOption) => number[], errors: Record<string, string> }>()
defineEmits<{ change: [ticketId: string, quantity: number] }>()

const price = (cents: number | null) => (cents === null ? '' : cents === 0 ? locale.free : formatCents(cents))
const remaining = (n: number) => (n === 1 ? locale.remainingOne : locale.remaining.replace('{n}', String(n)))
const limits = (ticket: TicketOption) => (ticket.min > 1 ? locale.perOrder.replace('{min}', String(ticket.min)).replace('{max}', String(ticket.max)) : locale.upTo.replace('{max}', String(ticket.max)))
</script>

<template>
  <ul class="flex flex-col gap-3" :aria-label="locale.ticketsLabel">
    <li
      v-for="ticket in tickets"
      :key="ticket.id"
      class="flex flex-wrap items-start justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-4"
      :class="{ 'opacity-70': !ticket.onSale }"
      :data-testid="`ticket-${ticket.id}`"
    >
      <div class="flex min-w-0 flex-1 basis-48 flex-col gap-1">
        <h3 class="font-semibold">{{ ticket.name }}</h3>
        <p v-if="ticket.description" class="text-sm text-(--ph-ink-2)">{{ ticket.description }}</p>
        <p v-if="ticket.onSale" class="text-sm">
          <b class="text-lg font-semibold">{{ price(ticket.priceCents) }}</b>
          <span class="text-(--ph-ink-3)"> · {{ ticket.endsAt ? locale.batchUntil.replace('{lote}', ticket.batchName ?? '').replace('{data}', formatDateTime(ticket.endsAt)) : ticket.batchName }}</span>
        </p>
        <p v-else-if="ticket.nextBatch?.opensAt" class="text-sm text-(--ph-ink-2)">
          {{ locale.opensAt.replace('{lote}', ticket.nextBatch.name).replace('{data}', formatDateTime(ticket.nextBatch.opensAt)).replace('{preco}', price(ticket.nextBatch.priceCents)) }}
        </p>
        <p class="flex flex-wrap gap-2 text-xs text-(--ph-ink-3)">
          <span v-if="ticket.onSale">{{ limits(ticket) }}</span>
          <span v-if="ticket.onSale && ticket.remaining !== null" class="badge badge-sm badge-warning badge-soft">{{ remaining(ticket.remaining) }}</span>
          <span v-if="!ticket.onSale" class="badge badge-sm badge-soft">{{ ticket.statusLabel }}</span>
        </p>
        <p v-if="errors[ticket.id]" :id="`qty-${ticket.id}-error`" class="text-xs text-error">{{ errors[ticket.id] }}</p>
      </div>
      <div v-if="ticket.onSale" class="flex flex-col gap-1">
        <label :for="`qty-${ticket.id}`" class="sr-only">{{ locale.quantity.replace('{nome}', ticket.name) }}</label>
        <select
          :id="`qty-${ticket.id}`"
          class="select w-24"
          :class="{ 'select-error': errors[ticket.id] }"
          :value="quantities[ticket.id] ?? 0"
          :aria-invalid="errors[ticket.id] ? 'true' : undefined"
          :aria-describedby="errors[ticket.id] ? `qty-${ticket.id}-error` : undefined"
          @change="$emit('change', ticket.id, Number(($event.target as HTMLSelectElement).value))"
        >
          <option v-for="quantity in options(ticket)" :key="quantity" :value="quantity">{{ quantity }}</option>
        </select>
      </div>
    </li>
  </ul>
</template>
