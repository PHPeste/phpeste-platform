<script setup lang="ts">
import { useEdition } from '@/application/shared/composables/useEdition'
import SpeakersPanel from '../components/SpeakersPanel.vue'

const { current, isReadOnly } = useEdition()
</script>

<template>
  <SpeakersPanel v-if="current" :key="current.id" :edition-id="current.id" :read-only="isReadOnly" />
</template>
