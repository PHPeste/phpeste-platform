<script setup lang="ts">
import { computed } from 'vue'
import { useEdition } from '@/application/shared/composables/useEdition'
import { useAuthStore } from '@/stores/authStore'
import CfpReviewPanel from '../components/CfpReviewPanel.vue'

const { current, role, isReadOnly } = useEdition()
const userId = computed(() => useAuthStore().user?.id ?? null)
</script>

<template>
  <CfpReviewPanel
    v-if="current"
    :key="`${current.id}-${role}`"
    :edition-id="current.id"
    :organizer="role === 'organizador'"
    :user-id="userId"
    :read-only="isReadOnly"
  />
</template>
