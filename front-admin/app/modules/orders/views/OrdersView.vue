<script setup lang="ts">
import { computed } from 'vue'
import { useEdition } from '@/application/shared/composables/useEdition'
import OrdersPanel from '../components/OrdersPanel.vue'

const { current, isReadOnly, role } = useEdition()
// Organização e financeiro veem e reembolsam; cobrar, marcar como pago e cancelar é da organização.
const canManage = computed(() => role.value === 'organizador')
const canRefund = computed(() => role.value === 'organizador' || role.value === 'financeiro')
</script>

<template>
  <OrdersPanel v-if="current" :key="current.id" :edition-id="current.id" :read-only="isReadOnly" :can-manage="canManage" :can-refund="canRefund" />
</template>
