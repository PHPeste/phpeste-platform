<script setup lang="ts">
import { useCurrentEdition } from '@/application/shared/composables/useCurrentEdition'
import OrderDetail from '../components/OrderDetail.vue'

const { edition } = useCurrentEdition()
const route = useRoute()
const orderId = String(route.params.id ?? '')

function lapsed(reason: 'expired' | 'cancelled') {
  navigateTo({ path: '/ingressos', query: { reserva: reason === 'expired' ? 'expirada' : 'cancelada' } }, { replace: true })
}
</script>

<template>
  <OrderDetail v-if="edition" :key="orderId" :edition-id="edition.id" :order-id="orderId" @lapsed="lapsed" />
</template>
