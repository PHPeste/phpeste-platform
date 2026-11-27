<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import TicketQr from '@/components/TicketQr.vue'
import { useCurrentEdition } from '@/application/shared/composables/useCurrentEdition'
import { useAuthStore } from '@/stores/authStore'
import BadgeForm from './BadgeForm.vue'
import TransferForm from './TransferForm.vue'
import { useTicket } from '../composables/useTicket'
import { downloadTicket } from '../composables/ticketImage'
import { keepTicketsOffline } from '../composables/offlineTickets'
import { formatDateTime } from '../composables/format'
import locale from '../locales/pt-br'

const props = defineProps<{ editionId: string, ticketId: string }>()
const auth = useAuthStore()
const { edition } = useCurrentEdition()
const detail = useTicket(props.editionId, props.ticketId)
const { ticket, state, offline, busy, form, transferredTo, load } = detail

const badgeErrors = ref<Record<string, string>>({})
const badgeStatus = ref<string | null>(null)
const transferErrors = ref<Record<string, string>>({})
const transferMessage = ref<string | null>(null)
const resendStatus = ref<string | null>(null)

onMounted(() => {
  load()
  keepTicketsOffline()
})
watch(() => auth.isAuthenticated, (value) => { if (value) load() })

async function save() {
  badgeStatus.value = null
  const result = await detail.save()
  badgeErrors.value = result.ok ? {} : result.fieldErrors
  badgeStatus.value = result.ok ? locale.saved : result.message
}

async function transfer(name: string, email: string) {
  transferMessage.value = null
  const result = await detail.transfer(name, email)
  transferErrors.value = result.ok ? {} : result.fieldErrors
  if (!result.ok && !Object.keys(result.fieldErrors).length) transferMessage.value = result.message
}

async function resend() {
  const email = ticket.value?.holderEmail ?? ''
  const result = await detail.resend()
  resendStatus.value = result.ok ? locale.resent.replace('{email}', email) : result.message
}

const print = () => window.print()
const saveImage = () => { if (ticket.value) downloadTicket(ticket.value, edition.value?.name ?? '') }
</script>

<template>
  <div class="flex flex-col gap-6">
    <NuxtLink to="/conta/ingressos" class="text-sm print:hidden">{{ locale.back }}</NuxtLink>
    <div v-if="state === 'loading' || state === 'idle'" class="skeleton h-80 w-full" role="status" aria-busy="true" />
    <div v-else-if="state === 'error'" role="alert" class="alert alert-error alert-soft flex flex-wrap justify-between gap-3">
      <span>{{ locale.loadError }}</span>
      <button type="button" class="btn btn-sm" @click="load">{{ locale.retry }}</button>
    </div>
    <p v-else-if="state === 'not-found'" role="alert" class="alert alert-warning alert-soft">{{ locale.notFound }}</p>
    <section v-else-if="state === 'transferred' && transferredTo" class="card flex flex-col gap-2 border border-(--ph-line) bg-(--ph-surface) p-6" aria-labelledby="transferred-title" data-testid="ticket-transferred">
      <h2 id="transferred-title" class="text-lg font-semibold">{{ locale.transferredTitle }}</h2>
      <p role="status">{{ locale.transferredText.replace('{nome}', transferredTo.name).replace('{email}', transferredTo.email) }}</p>
    </section>
    <template v-else-if="ticket">
      <p v-if="offline" role="status" class="alert alert-info alert-soft text-sm print:hidden">{{ locale.offlineTicket }}</p>
      <section class="card flex flex-col items-center gap-3 border border-(--ph-line) bg-(--ph-surface) p-6 text-center" aria-labelledby="ticket-title" data-testid="ticket-detail">
        <span class="text-xs text-(--ph-ink-3)">{{ edition?.name }} · {{ ticket.typeName }}</span>
        <h2 id="ticket-title" class="text-2xl font-semibold">{{ ticket.holderName }}</h2>
        <TicketQr v-if="ticket.qr" :value="ticket.qr" :label="locale.ticketQr.replace('{nome}', ticket.holderName)" size="size-64" />
        <p v-else class="text-error">{{ locale.cancelled }}</p>
        <span class="font-mono text-sm">{{ locale.ticketCode.replace('{codigo}', ticket.code) }}</span>
        <span v-if="ticket.qr" class="text-sm text-(--ph-ink-2)">{{ locale.showAtEntrance }}</span>
        <div v-if="ticket.qr" class="flex flex-wrap justify-center gap-2 print:hidden">
          <button type="button" class="btn btn-ghost btn-sm" @click="saveImage">{{ locale.saveImage }}</button>
          <button type="button" class="btn btn-ghost btn-sm" @click="print">{{ locale.print }}</button>
          <button type="button" class="btn btn-ghost btn-sm" :disabled="offline || busy === 'resend'" @click="resend">{{ locale.resend }}</button>
        </div>
        <p v-if="resendStatus" role="status" class="text-sm print:hidden">{{ resendStatus }}</p>
      </section>
      <template v-if="ticket.active && !offline">
        <BadgeForm :form="form" :errors="badgeErrors" :busy="busy === 'save'" :status="badgeStatus" @submit="save" />
        <TransferForm :current-email="ticket.holderEmail" :busy="busy === 'transfer'" :errors="transferErrors" :message="transferMessage" @transfer="transfer" />
      </template>
      <section v-if="ticket.transfers.length" class="flex flex-col gap-2 print:hidden" aria-labelledby="history-title">
        <h2 id="history-title" class="text-lg font-semibold">{{ locale.historyTitle }}</h2>
        <ul class="flex flex-col gap-1 text-sm text-(--ph-ink-2)">
          <li v-for="(move, index) in ticket.transfers" :key="index">
            {{ locale.historyItem.replace('{de}', move.fromName).replace('{para}', move.toName) }}<template v-if="move.at"> · {{ formatDateTime(move.at) }}</template>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>
