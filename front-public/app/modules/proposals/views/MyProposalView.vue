<script setup lang="ts">
import { useRoute } from 'vue-router'
import { useCurrentEdition } from '@/application/shared/composables/useCurrentEdition'
import MyProposal from '../components/MyProposal.vue'

const route = useRoute()
const { edition } = useCurrentEdition()
</script>

<template>
  <MyProposal v-if="edition" :id="String(route.params.id)" :key="String(route.params.id)" :edition-id="edition.id" />
</template>
