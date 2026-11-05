<script setup lang="ts">
import { useAuthStore } from '@/stores/authStore'
import { useEdition } from '@/application/shared/composables/useEdition'

const authStore = useAuthStore()
const { current } = useEdition()
</script>

<template>
  <section class="flex flex-col gap-2">
    <h1 class="text-3xl font-semibold">Visão geral</h1>
    <p class="text-sm text-(--ph-ink-2)">
      Olá, {{ authStore.user?.name }}.
      <template v-if="current">Você está em {{ current.name }}.</template>
    </p>
  </section>
</template>
