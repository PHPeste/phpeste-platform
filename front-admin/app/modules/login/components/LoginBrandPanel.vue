<script setup lang="ts">
import locale from '../locales/pt-br'
</script>

<template>
  <section class="flex min-h-80 flex-1 basis-120 flex-col gap-0 bg-(--ph-ink) text-(--ph-on-brand)">
    <PhStripe />
    <div class="flex flex-col gap-7 px-8 py-12 md:px-14">
      <div class="flex items-center gap-3">
        <span class="flex size-11 items-center justify-center rounded-xl bg-(--ph-accent) text-lg font-semibold text-(--ph-ink)" aria-hidden="true">Ph</span>
        <span class="text-lg font-semibold">{{ locale.brandName }}</span>
      </div>
      <p class="max-w-130 text-4xl leading-tight font-semibold md:text-5xl">{{ locale.brandTitle }}</p>
      <p class="max-w-115 text-base text-(--ph-side-text)">{{ locale.brandSubtitle }}</p>
    </div>
  </section>
</template>
