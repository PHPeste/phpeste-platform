<script setup lang="ts">
import { useCurrentEdition } from '@/application/shared/composables/useCurrentEdition'
import AccountLayout from '../components/AccountLayout.vue'
import TicketDetail from '../components/TicketDetail.vue'
import locale from '../locales/pt-br'

const { edition } = useCurrentEdition()
const route = useRoute()
const ticketId = String(route.params.id)
useHead({ title: locale.ticketTitle })
</script>

<template>
  <AccountLayout :title="locale.ticketTitle">
    <TicketDetail v-if="edition" :edition-id="edition.id" :ticket-id="ticketId" />
  </AccountLayout>
</template>
