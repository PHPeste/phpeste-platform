<script setup lang="ts">
import { useEdition } from '@/application/shared/composables/useEdition'
import TeamPanel from '../components/TeamPanel.vue'

const { current, isReadOnly } = useEdition()
</script>

<template>
  <TeamPanel v-if="current" :key="current.id" :edition-id="current.id" :read-only="isReadOnly" />
</template>
