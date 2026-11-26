<script setup lang="ts">
import locale from '../locales/pt-br'
import type { Preview } from '../types'

defineProps<{ label: string, preview: Preview | null, state: 'idle' | 'loading' | 'ready' | 'blocked' | 'error' }>()
defineEmits<{ retry: [] }>()
</script>

<template>
  <div class="flex flex-col gap-2" data-testid="email-preview">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
      <h3 class="text-xs font-medium tracking-[.08em] text-(--ph-ink-3) uppercase">{{ locale.previewTitle }}</h3>
      <span v-if="state === 'loading'" role="status" class="text-xs text-(--ph-ink-3)">{{ locale.previewLoading }}</span>
    </div>
    <p class="text-xs text-(--ph-ink-3)">{{ locale.previewHint }}</p>

    <p v-if="state === 'blocked'" class="alert alert-warning alert-soft text-sm">{{ locale.previewBlocked }}</p>
    <div v-else-if="state === 'error'" class="alert alert-error alert-soft flex flex-wrap items-center justify-between gap-2 text-sm">
      <span>{{ locale.previewError }}</span>
      <PhButton variant="secondary" @click="$emit('retry')">{{ locale.previewRetry }}</PhButton>
    </div>
    <div v-else-if="!preview" class="skeleton h-96 w-full" aria-hidden="true" />

    <template v-if="preview && state !== 'blocked'">
      <p class="text-sm font-medium" data-testid="preview-subject">{{ locale.previewSubject.replace('{assunto}', preview.subject) }}</p>
      <iframe
        :title="locale.previewFrame.replace('{modelo}', label)"
        :srcdoc="preview.html"
        sandbox=""
        class="h-[32rem] w-full rounded-(--ph-radius) border border-(--ph-line) bg-white transition-opacity"
        :class="{ 'opacity-60': state === 'loading' }"
      />
    </template>
  </div>
</template>
