<script setup lang="ts">
import { useRoute } from 'vue-router'
import { useEdition } from '@/application/shared/composables/useEdition'
import SponsorDetailPanel from '../components/SponsorDetailPanel.vue'

const route = useRoute()
const { current, isReadOnly } = useEdition()
</script>

<template>
  <SponsorDetailPanel v-if="current" :key="`${current.id}-${route.params.id}`" :edition-id="current.id" :sponsor-id="String(route.params.id)" :read-only="isReadOnly" />
</template>
