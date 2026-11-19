<script setup lang="ts">
import { computed } from 'vue'
import { useEdition } from '@/application/shared/composables/useEdition'
import TicketsPanel from '../components/TicketsPanel.vue'

const { current, isReadOnly, role } = useEdition()
// O financeiro vê os ingressos, mas só a organização muda o que é vendido.
const canEdit = computed(() => role.value === 'organizador')
</script>

<template>
  <TicketsPanel v-if="current" :key="current.id" :edition-id="current.id" :read-only="isReadOnly" :can-edit="canEdit" />
</template>
