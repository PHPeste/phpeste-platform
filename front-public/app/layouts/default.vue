<script setup lang="ts">
import locale from '@/application/locales/pt-br/site'
</script>

<template>
  <div class="flex min-h-dvh flex-col">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-(--ph-surface) focus:px-3 focus:py-2">{{ locale.skipToContent }}</a>
    <SiteHeader />
    <main id="conteudo" class="mx-auto w-full max-w-5xl flex-1 px-5 py-10">
      <slot />
    </main>
    <footer class="border-t border-(--ph-line) px-5 py-6 text-center text-xs text-(--ph-ink-3)">{{ locale.platform }}</footer>
  </div>
</template>
