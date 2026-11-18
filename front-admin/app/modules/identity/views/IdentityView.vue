<script setup lang="ts">
import { computed } from 'vue'
import { useEdition } from '@/application/shared/composables/useEdition'
import ThemeEditor from '../components/ThemeEditor.vue'

const { current, isReadOnly } = useEdition()
const dates = computed(() => [current.value?.city, current.value?.state].filter(Boolean).join(' · '))
</script>

<template>
  <ThemeEditor
    v-if="current"
    :key="current.id"
    :edition-id="current.id"
    :edition-name="current.name"
    :dates="dates"
    :read-only="isReadOnly"
  />
</template>
