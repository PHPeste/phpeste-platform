<script setup lang="ts">
import { useRoute } from 'vue-router'
import { useEdition } from '@/application/shared/composables/useEdition'
import SpeakerDetailPanel from '../components/SpeakerDetailPanel.vue'

const route = useRoute()
const { current, isReadOnly } = useEdition()
</script>

<template>
  <SpeakerDetailPanel v-if="current" :key="`${current.id}-${route.params.id}`" :edition-id="current.id" :user-id="String(route.params.id)" :read-only="isReadOnly" />
</template>
