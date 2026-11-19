<script setup lang="ts">
import { useEdition } from '@/application/shared/composables/useEdition'
import PaymentsPanel from '../components/PaymentsPanel.vue'

// Chaves do gateway são só da organização (payments.gateway no backend).
const { current, isReadOnly } = useEdition()
</script>

<template>
  <PaymentsPanel v-if="current" :key="current.id" :edition-id="current.id" :read-only="isReadOnly" />
</template>
