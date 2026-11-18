<script setup lang="ts">
import { computed } from 'vue'
import locale from '../locales/pt-br'
import type { SpeakerDetail } from '../types'

const props = defineProps<{ speaker: SpeakerDetail }>()
const role = computed(() => [props.speaker.jobTitle, props.speaker.company].filter(Boolean).join(' · '))
const place = computed(() => [props.speaker.city, props.speaker.state].filter(Boolean).join(' · '))
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-5">
      <div class="flex flex-col gap-1">
        <h2 class="text-lg font-semibold">{{ locale.profileTitle }}</h2>
        <p class="text-sm text-(--ph-ink-2)">{{ locale.profileHint }}</p>
      </div>
      <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-[10rem_1fr]">
        <dt class="text-sm font-medium">{{ locale.displayName }}</dt>
        <dd>{{ speaker.displayName }}</dd>
        <dt class="text-sm font-medium">{{ locale.jobAndCompany }}</dt>
        <dd :class="{ 'text-(--ph-ink-3)': !role }">{{ role || locale.noValue }}</dd>
        <dt class="text-sm font-medium">{{ locale.location }}</dt>
        <dd :class="{ 'text-(--ph-ink-3)': !place }">{{ place || locale.noValue }}</dd>
        <dt class="text-sm font-medium">{{ locale.bio }}</dt>
        <dd class="whitespace-pre-line" :class="{ 'text-(--ph-ink-3)': !speaker.bio }">{{ speaker.bio || locale.noValue }}</dd>
        <dt class="text-sm font-medium">{{ locale.links }}</dt>
        <dd>
          <ul v-if="speaker.links.length" class="flex flex-wrap gap-3">
            <li v-for="link in speaker.links" :key="link.url"><a :href="link.url" target="_blank" rel="noopener noreferrer" class="link">{{ link.label }}</a></li>
          </ul>
          <span v-else class="text-(--ph-ink-3)">{{ locale.noValue }}</span>
        </dd>
        <dt class="text-sm font-medium">{{ locale.photo }}</dt>
        <dd>
          <img v-if="speaker.photoUrl" :src="speaker.photoUrl" :alt="speaker.displayName" class="size-24 rounded-(--ph-radius) object-cover">
          <span v-else class="text-(--ph-ink-3)">{{ locale.noPhoto }}</span>
        </dd>
      </dl>
    </div>
  </PhCard>
</template>
