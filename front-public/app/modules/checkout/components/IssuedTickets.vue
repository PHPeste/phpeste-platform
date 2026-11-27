<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue'
import TicketQr from '@/components/TicketQr.vue'
import { useIssuedTickets } from '../composables/useIssuedTickets'
import locale from '../locales/pt-br'

const props = defineProps<{ editionId: string, orderId: string }>()
const { tickets, state, load, stop } = useIssuedTickets(props.editionId, props.orderId)

onMounted(load)
onBeforeUnmount(stop)
</script>

<template>
  <section class="flex flex-col gap-3" aria-labelledby="issued-title" data-testid="issued-tickets">
    <h2 id="issued-title" class="text-lg font-semibold">{{ locale.itemsTitle }}</h2>
    <p v-if="state === 'loading'" role="status" class="flex items-center gap-2 text-sm"><span class="loading loading-spinner loading-sm" aria-hidden="true" />{{ locale.ticketsLoading }}</p>
    <ul v-else class="grid gap-3 sm:grid-cols-2">
      <li v-for="ticket in tickets" :key="ticket.id" class="flex flex-col items-center gap-2 rounded-box border border-base-300 bg-base-100 p-4 text-center">
        <b class="font-semibold">{{ ticket.holderName }}</b>
        <span class="text-sm text-(--ph-ink-2)">{{ ticket.typeName }}</span>
        <TicketQr v-if="ticket.qr" :value="ticket.qr" :label="locale.ticketQr.replace('{nome}', ticket.holderName)" />
        <span class="font-mono text-xs text-(--ph-ink-3)">{{ locale.ticketCode.replace('{codigo}', ticket.code) }}</span>
        <span class="text-xs text-(--ph-ink-3)">{{ locale.showAtEntrance }}</span>
      </li>
    </ul>
  </section>
</template>
