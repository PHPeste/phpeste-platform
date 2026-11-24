<script setup lang="ts">
import { useEdition } from '@/application/shared/composables/useEdition'
import FinancePanel from '../components/FinancePanel.vue'

const { current, isReadOnly } = useEdition()
</script>

<template>
  <FinancePanel v-if="current" :key="current.id" :edition-id="current.id" :read-only="isReadOnly" />
</template>
