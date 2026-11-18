<script setup lang="ts">
import { useCurrentEdition } from '@/application/shared/composables/useCurrentEdition'

const { edition, unavailable } = useCurrentEdition()

useHead(() => ({ titleTemplate: (title?: string) => (title ? `${title} · ${edition.value?.name ?? 'PHPeste'}` : edition.value?.name ?? 'PHPeste') }))
</script>

<template>
  <div>
    <NuxtRouteAnnouncer />
    <UnknownSite v-if="!edition" :unavailable="unavailable" />
    <NuxtLayout v-else>
      <NuxtPage />
    </NuxtLayout>
  </div>
</template>
