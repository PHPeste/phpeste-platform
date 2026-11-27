<script setup lang="ts">
import { useCurrentEdition } from '@/application/shared/composables/useCurrentEdition'
import AccountLayout from '../components/AccountLayout.vue'
import MyOrders from '../components/MyOrders.vue'
import locale from '../locales/pt-br'

const { edition } = useCurrentEdition()
useHead({ title: locale.ordersTitle })
</script>

<template>
  <AccountLayout :title="locale.ordersTitle" :intro="locale.ordersIntro">
    <MyOrders v-if="edition" :edition-id="edition.id" />
  </AccountLayout>
</template>
