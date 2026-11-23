<script setup lang="ts">
import { useEdition } from '@/application/shared/composables/useEdition'
import SponsorsPanel from '../components/SponsorsPanel.vue'

const { current, isReadOnly } = useEdition()
</script>

<template>
  <SponsorsPanel v-if="current" :key="current.id" :edition-id="current.id" :read-only="isReadOnly" />
</template>
