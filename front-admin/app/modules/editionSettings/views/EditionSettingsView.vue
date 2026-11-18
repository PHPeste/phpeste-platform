<script setup lang="ts">
import { useEdition } from '@/application/shared/composables/useEdition'
import EditionSettingsPanel from '../components/EditionSettingsPanel.vue'

const { current, isReadOnly } = useEdition()
</script>

<template>
  <EditionSettingsPanel v-if="current" :key="current.id" :edition-id="current.id" :read-only="isReadOnly" />
</template>
