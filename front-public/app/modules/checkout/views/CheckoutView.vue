<script setup lang="ts">
import { computed } from 'vue'
import { useCurrentEdition } from '@/application/shared/composables/useCurrentEdition'
import CheckoutFlow from '../components/CheckoutFlow.vue'

const { edition } = useCurrentEdition()
const route = useRoute()
const notice = computed(() => (route.query.reserva === 'expirada' ? 'expired' : route.query.reserva === 'cancelada' ? 'cancelled' : null))

function reserved(orderId: string) {
  navigateTo(`/checkout/pedido/${orderId}`)
}
</script>

<template>
  <CheckoutFlow v-if="edition" :key="edition.id" :edition-id="edition.id" :notice="notice" @reserved="reserved" />
</template>
