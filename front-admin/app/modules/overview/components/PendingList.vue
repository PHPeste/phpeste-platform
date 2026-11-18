<script setup lang="ts">
import locale from '../locales/pt-br'
import type { PendingItem } from '../types'

defineProps<{ items: PendingItem[] }>()
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-2">
      <h2 class="text-lg font-semibold">{{ locale.pendingTitle }}</h2>
      <p v-if="items.length === 0" class="py-4 text-sm text-(--ph-ink-2)">{{ locale.pendingEmpty }}</p>
      <ul v-else class="flex flex-col">
        <li v-for="item in items" :key="item.key" class="flex items-center justify-between gap-3 border-t border-(--ph-line) py-3 first:border-t-0">
          <div class="flex min-w-0 flex-1 flex-col gap-1">
            <span class="font-medium">{{ item.title }}</span>
            <PhBadge :status="item.tone" class="self-start">{{ item.tag }}</PhBadge>
          </div>
          <PhButton variant="ghost" class="shrink-0" :to="item.actionPath" :aria-label="`${item.actionLabel}: ${item.title}`">
            {{ item.actionLabel }}
          </PhButton>
        </li>
      </ul>
    </div>
  </PhCard>
</template>
