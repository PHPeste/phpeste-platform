<script setup lang="ts">
import { useEdition } from '@/application/shared/composables/useEdition'
import CommunicationPanel from '../components/CommunicationPanel.vue'

const { current, isReadOnly } = useEdition()
</script>

<template>
  <CommunicationPanel v-if="current" :key="current.id" :edition-id="current.id" :read-only="isReadOnly" />
</template>
