<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { useCurrentEdition } from '@/application/shared/composables/useCurrentEdition'
import { useAuthStore } from '@/stores/authStore'
import TicketCard from './TicketCard.vue'
import VerifyEmailNotice from './VerifyEmailNotice.vue'
import { useMyTickets } from '../composables/useMyTickets'
import { downloadTicket } from '../composables/ticketImage'
import { keepTicketsOffline } from '../composables/offlineTickets'
import { formatDateTime } from '../composables/format'
import locale from '../locales/pt-br'
import type { AccountTicket } from '../types'

const props = defineProps<{ editionId: string }>()
const auth = useAuthStore()
const { edition } = useCurrentEdition()
const { tickets, state, savedAt, emailVerified, load } = useMyTickets(props.editionId)

onMounted(() => {
  load()
  keepTicketsOffline()
})
watch(() => auth.isAuthenticated, (value) => { if (value) load() })

const save = (ticket: AccountTicket) => downloadTicket(ticket, edition.value?.name ?? '')
const print = () => window.print()
</script>

<template>
  <div class="flex flex-col gap-4">
    <div v-if="state === 'loading' || state === 'idle'" class="flex flex-col gap-3" role="status" aria-busy="true">
      <div v-for="n in 2" :key="n" class="skeleton h-40 w-full" />
    </div>
    <div v-else-if="state === 'error'" role="alert" class="alert alert-error alert-soft flex flex-wrap justify-between gap-3">
      <span>{{ locale.loadError }}</span>
      <button type="button" class="btn btn-sm" @click="load">{{ locale.retry }}</button>
    </div>
    <template v-else>
      <p v-if="savedAt" role="status" class="alert alert-info alert-soft text-sm print:hidden" data-testid="offline-notice">{{ locale.offlineNotice.replace('{data}', formatDateTime(savedAt)) }}</p>
      <VerifyEmailNotice v-if="!emailVerified && auth.user" :email="auth.user.email" />
      <div v-if="!tickets.length" class="card flex flex-col items-start gap-3 border border-(--ph-line) bg-(--ph-surface) p-6">
        <h2 class="text-lg font-semibold">{{ locale.noTickets }}</h2>
        <p class="text-sm text-(--ph-ink-2)">{{ locale.noTicketsHint }}</p>
        <NuxtLink to="/ingressos" class="btn btn-primary">{{ locale.buyTickets }}</NuxtLink>
      </div>
      <template v-else>
        <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
          <p class="text-sm text-(--ph-ink-2)">{{ locale.saveImageHint }}</p>
          <button type="button" class="btn btn-ghost btn-sm" @click="print">{{ locale.print }}</button>
        </div>
        <TicketCard v-for="ticket in tickets" :key="ticket.id" :ticket="ticket" @save="save" />
      </template>
    </template>
  </div>
</template>
