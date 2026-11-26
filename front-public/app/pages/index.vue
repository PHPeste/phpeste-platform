<script setup lang="ts">
import { useCurrentEdition } from '@/application/shared/composables/useCurrentEdition'
import locale from '@/application/locales/pt-br/site'

const { edition } = useCurrentEdition()
</script>

<template>
  <section class="flex flex-col gap-4">
    <span class="text-xs font-medium tracking-wider text-(--ph-terra) uppercase">{{ locale.homeLead }}</span>
    <h1 class="text-4xl leading-tight font-semibold">{{ edition?.name }}</h1>
    <p class="max-w-xl text-(--ph-ink-2)">{{ locale.homeText }}</p>
    <div class="flex flex-wrap gap-3">
      <NuxtLink to="/ingressos" class="btn btn-primary">{{ locale.homeTickets }}</NuxtLink>
      <NuxtLink to="/cfp" class="btn btn-secondary">{{ locale.homeCfp }}</NuxtLink>
      <NuxtLink to="/palestrante/propostas" class="btn btn-ghost">{{ locale.homeMine }}</NuxtLink>
    </div>
  </section>
</template>
