<script setup lang="ts">
import { computed } from 'vue'
import locale from '../locales/pt-br'
import type { CfpInfo, ProfileForm, TalkForm } from '../types'

const props = defineProps<{ cfp: CfpInfo, profile: ProfileForm, talk: TalkForm, missing: string[] }>()
const emit = defineEmits<{ edit: [step: 1 | 2] }>()

const label = (items: Array<{ value: string, label: string }>, value: string) => items.find((item) => item.value === value)?.label ?? '—'
const where = computed(() => [props.profile.city, props.profile.state].filter(Boolean).join(' · '))
</script>

<template>
  <div class="flex flex-col gap-5">
    <h2 class="text-xl font-semibold">{{ locale.reviewTitle }}</h2>
    <div v-if="missing.length" role="alert" class="alert alert-warning alert-soft flex flex-col items-start gap-1 text-sm">
      <b>{{ locale.missingTitle }}</b>
      <span>{{ missing.map((field) => locale.fields[field] ?? field).join(', ') }}</span>
    </div>
    <section class="card border border-(--ph-line) bg-(--ph-surface) p-5" data-testid="review-profile">
      <div class="flex items-center justify-between gap-3">
        <h3 class="font-semibold">{{ locale.steps[1] }}</h3>
        <button type="button" class="btn btn-ghost btn-sm" @click="emit('edit', 1)">{{ locale.edit }}</button>
      </div>
      <p>{{ profile.displayName }}<template v-if="profile.jobTitle"> · {{ profile.jobTitle }}</template><template v-if="profile.company"> · {{ profile.company }}</template></p>
      <p v-if="where" class="text-sm text-(--ph-ink-2)">{{ where }}</p>
    </section>
    <section class="card border border-(--ph-line) bg-(--ph-surface) p-5" data-testid="review-talk">
      <div class="flex items-center justify-between gap-3">
        <h3 class="font-semibold">{{ locale.steps[2] }}</h3>
        <button type="button" class="btn btn-ghost btn-sm" @click="emit('edit', 2)">{{ locale.edit }}</button>
      </div>
      <p class="text-lg font-medium">{{ talk.title }}</p>
      <p class="text-sm text-(--ph-ink-2)">{{ [label(cfp.tracks, talk.trackId), label(cfp.formats, talk.format), label(cfp.levels, talk.level), label(cfp.languages, talk.language)].join(' · ') }}</p>
      <h4 class="mt-3 text-sm font-semibold">{{ locale.summaryLabel }}</h4>
      <p class="whitespace-pre-line text-sm">{{ talk.summary }}</p>
      <h4 class="mt-3 text-sm font-semibold">{{ locale.audienceLabel }}</h4>
      <p class="whitespace-pre-line text-sm">{{ talk.audience }}</p>
    </section>
    <p v-if="cfp.anonymousReview" class="text-sm text-(--ph-ink-2)">{{ locale.anonymous }}</p>
  </div>
</template>
