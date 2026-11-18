<script setup lang="ts">
import { computed } from 'vue'
import locale from '../locales/pt-br'

const props = defineProps<{ colors: Record<string, string>, font: string, radius: number, logo: string | null, editionName: string, dates: string }>()

const style = computed(() => ({
  '--p-bg': props.colors.bg,
  '--p-surface': props.colors.surface,
  '--p-ink': props.colors.ink,
  '--p-ink-2': props.colors.ink_2,
  '--p-line': props.colors.line,
  '--p-brand': props.colors.brand,
  '--p-on-brand': props.colors.on_brand,
  '--p-accent': props.colors.accent,
  '--p-support': props.colors.support,
  '--p-radius': `${props.radius}px`,
  fontFamily: `'${props.font}', system-ui, sans-serif`,
}))

useHead(() => ({
  link: [{ rel: 'stylesheet', href: `https://fonts.googleapis.com/css2?family=${encodeURIComponent(props.font).replace(/%20/g, '+')}:wght@400;500;600&display=swap` }],
}))
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-4">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">{{ locale.previewTitle }}</h2>
        <PhBadge :dot="false">{{ locale.previewBadge }}</PhBadge>
      </div>
      <div data-testid="theme-preview" :style="style" class="overflow-hidden rounded-xl border border-(--ph-line) bg-(--p-bg) text-(--p-ink)" aria-hidden="true">
        <div class="h-2" style="background: repeating-linear-gradient(90deg, var(--p-accent) 0 16px, var(--p-support) 16px 32px)" />
        <div class="flex items-center justify-between gap-3 border-b border-(--p-line) px-5 py-4">
          <img v-if="logo" :src="logo" alt="" class="h-7 max-w-40 object-contain">
          <b v-else class="font-semibold">{{ editionName }}</b>
          <span class="text-xs">{{ locale.previewNav }}</span>
        </div>
        <div class="flex flex-col gap-3.5 px-5 py-8">
          <span class="text-xs font-medium tracking-wider text-(--p-support) uppercase">{{ dates || locale.previewEyebrow }}</span>
          <b class="text-3xl leading-tight font-semibold">{{ locale.previewHeadline }}</b>
          <span class="text-sm text-(--p-ink-2)">{{ locale.previewText }}</span>
          <div class="mt-1.5 flex flex-wrap gap-2.5">
            <span class="inline-flex min-h-11 items-center rounded-(--p-radius) bg-(--p-brand) px-4.5 font-medium text-(--p-on-brand)">{{ locale.previewPrimary }}</span>
            <span class="inline-flex min-h-11 items-center rounded-(--p-radius) border border-(--p-line) px-4.5 font-medium">{{ locale.previewSecondary }}</span>
          </div>
        </div>
        <div class="mx-5 mb-6 flex items-center justify-between gap-3 rounded-xl border border-(--p-line) bg-(--p-surface) px-4.5 py-4">
          <div>
            <b class="font-medium">{{ locale.previewTicket }}</b>
            <div class="text-xs text-(--p-ink-2)">{{ locale.previewTicketHint }}</div>
          </div>
          <b class="text-lg font-semibold text-(--p-brand)">{{ locale.previewPrice }}</b>
        </div>
      </div>
    </div>
  </PhCard>
</template>
