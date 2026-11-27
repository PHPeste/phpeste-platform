<script setup lang="ts">
import { useCurrentEdition } from '@/application/shared/composables/useCurrentEdition'
import AccountLayout from '../components/AccountLayout.vue'
import MyTickets from '../components/MyTickets.vue'
import locale from '../locales/pt-br'

const { edition } = useCurrentEdition()
useHead({ title: locale.ticketsTitle })
</script>

<template>
  <AccountLayout :title="locale.ticketsTitle" :intro="locale.ticketsIntro">
    <MyTickets v-if="edition" :edition-id="edition.id" />
  </AccountLayout>
</template>
