<script setup lang="ts">
import locale from '../locales/pt-br'
import { STATUS_TONE } from './statusTone'
import type { SpeakerTalk } from '../types'

defineProps<{ talks: SpeakerTalk[] }>()
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-3">
      <h2 class="text-lg font-semibold">{{ locale.talksTitle }}</h2>
      <ul class="flex flex-col">
        <li v-for="talk in talks" :key="talk.id" class="flex flex-wrap items-center justify-between gap-3 border-t border-(--ph-line) py-3 first:border-t-0">
          <div class="flex flex-col">
            <b class="font-medium">{{ talk.title }}</b>
            <span class="text-sm text-(--ph-ink-2)">
              {{ [`#${talk.number}`, talk.track, talk.durationMinutes ? locale.minutes.replace('{n}', String(talk.durationMinutes)) : talk.format].filter(Boolean).join(' · ') }}
            </span>
          </div>
          <PhBadge :status="STATUS_TONE[talk.status]">{{ talk.statusLabel }}</PhBadge>
        </li>
      </ul>
    </div>
  </PhCard>
</template>
