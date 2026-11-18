<script setup lang="ts">
import { useEdition } from '@/application/shared/composables/useEdition'
import CfpSettingsPanel from '../components/CfpSettingsPanel.vue'

const { current, isReadOnly } = useEdition()
</script>

<template>
  <CfpSettingsPanel v-if="current" :key="current.id" :edition-id="current.id" :read-only="isReadOnly" />
</template>
