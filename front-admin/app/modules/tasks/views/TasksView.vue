<script setup lang="ts">
import { useEdition } from '@/application/shared/composables/useEdition'
import TasksPanel from '../components/TasksPanel.vue'

const { current, isReadOnly } = useEdition()
</script>

<template>
  <TasksPanel v-if="current" :key="current.id" :edition-id="current.id" :read-only="isReadOnly" />
</template>
