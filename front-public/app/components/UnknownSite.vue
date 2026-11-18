<script setup lang="ts">
import locale from '@/application/locales/pt-br/site'

defineProps<{ unavailable: boolean }>()
</script>

<template>
  <main id="conteudo" class="mx-auto flex min-h-dvh max-w-xl flex-col items-start justify-center gap-4 px-6" data-testid="unknown-site">
    <h1 class="text-2xl font-semibold">{{ unavailable ? locale.unavailableTitle : locale.unknownTitle }}</h1>
    <p class="text-(--ph-ink-2)">{{ unavailable ? locale.unavailableText : locale.unknownText }}</p>
    <a v-if="unavailable" href="" class="btn btn-primary">{{ locale.retry }}</a>
    <p class="text-xs text-(--ph-ink-3)">{{ locale.platform }}</p>
  </main>
</template>
