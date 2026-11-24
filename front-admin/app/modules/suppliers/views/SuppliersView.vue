<script setup lang="ts">
import { useEdition } from '@/application/shared/composables/useEdition'
import SuppliersPanel from '../components/SuppliersPanel.vue'

const { current, isReadOnly } = useEdition()
</script>

<template>
  <SuppliersPanel v-if="current" :key="current.id" :edition-id="current.id" :read-only="isReadOnly" />
</template>
