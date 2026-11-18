<script setup lang="ts">
import locale from '../locales/pt-br'
import type { CommunitySummary } from '../types'

defineProps<{ community: CommunitySummary }>()
const emit = defineEmits<{ open: [editionId: string], newEdition: [community: CommunitySummary] }>()
</script>

<template>
  <section class="rounded-(--radius-box) border border-(--ph-line) bg-(--ph-surface) px-5 pt-2 pb-3 md:px-7" :aria-labelledby="`community-${community.id}`">
    <div class="flex flex-wrap items-center justify-between gap-3 pt-5 pb-4">
      <div class="flex items-center gap-3.5">
        <span class="flex size-12 items-center justify-center rounded-xl bg-(--ph-accent) font-semibold text-(--ph-ink)" aria-hidden="true">{{ community.initials }}</span>
        <div>
          <h2 :id="`community-${community.id}`" class="text-lg font-semibold">{{ community.name }}</h2>
          <p class="text-xs text-(--ph-ink-3)">{{ [community.slug, community.roleLabel].filter(Boolean).join(' · ') }}</p>
        </div>
      </div>
    </div>

    <ul class="flex flex-col">
      <li v-for="edition in community.editions" :key="edition.id" class="flex flex-wrap items-center justify-between gap-3 border-t border-(--ph-line) py-4">
        <div class="flex flex-col gap-1">
          <span class="text-base font-semibold">{{ edition.name }}</span>
          <span class="text-xs text-(--ph-ink-3)">{{ [edition.dates, edition.place].filter(Boolean).join(' · ') }}</span>
        </div>
        <div class="flex items-center gap-3">
          <PhBadge :status="edition.statusTone" :dot="!edition.isClosed">{{ edition.statusLabel }}</PhBadge>
          <PhButton :variant="edition.isClosed ? 'ghost' : 'primary'" @click="emit('open', edition.id)">
            {{ edition.isClosed ? locale.history : locale.open }}
            <span class="sr-only">{{ edition.name }}</span>
          </PhButton>
        </div>
      </li>
      <li v-if="community.editions.length === 0" class="border-t border-(--ph-line) py-4 text-sm text-(--ph-ink-2)">
        {{ locale.noEditionsInCommunity }}
      </li>
      <li v-if="community.canManage" class="flex flex-wrap items-center justify-between gap-3 border-t border-(--ph-line) py-4">
        <span class="text-xs text-(--ph-ink-3)">{{ locale.newEditionHint }}</span>
        <PhButton variant="ghost" @click="emit('newEdition', community)">
          {{ locale.newEdition }}
          <span class="sr-only">{{ community.name }}</span>
        </PhButton>
      </li>
    </ul>
  </section>
</template>
