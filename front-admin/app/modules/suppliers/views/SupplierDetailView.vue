<script setup lang="ts">
import { useRoute } from 'vue-router'
import { useEdition } from '@/application/shared/composables/useEdition'
import SupplierDetailPanel from '../components/SupplierDetailPanel.vue'

const route = useRoute()
const { current, isReadOnly } = useEdition()
</script>

<template>
  <SupplierDetailPanel v-if="current" :key="`${current.id}-${route.params.id}`" :edition-id="current.id" :link-id="String(route.params.id)" :read-only="isReadOnly" />
</template>
