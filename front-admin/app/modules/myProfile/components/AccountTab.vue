<script setup lang="ts">
import { computed, ref } from 'vue'
import locale from '../locales/pt-br'
import type { Account } from '../types'

const props = defineProps<{ account: Account, errors: Record<string, string>, photoBusy: boolean }>()
const form = defineModel<{ name: string, locale: string }>({ required: true })
const emit = defineEmits<{ upload: [file: File], remove: [] }>()

const input = ref<HTMLInputElement | null>(null)
const initials = computed(() => props.account.name.split(/\s+/).filter(Boolean).slice(0, 2).map((word) => word[0]!.toUpperCase()).join(''))
const languages = Object.entries(locale.languages).map(([value, label]) => ({ value, label }))

function onFile(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (file) emit('upload', file)
  target.value = ''
}
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-5">
      <h2 class="text-lg font-semibold">{{ locale.accountTitle }}</h2>
      <div class="flex flex-wrap items-center gap-4">
        <img v-if="account.avatarUrl" :src="account.avatarUrl" :alt="locale.photo" class="size-20 rounded-full object-cover">
        <span v-else class="grid size-20 place-items-center rounded-full bg-(--ph-accent) text-2xl font-semibold text-(--ph-ink)" aria-hidden="true">{{ initials }}</span>
        <div class="flex flex-col gap-2">
          <div class="flex flex-wrap gap-2">
            <input ref="input" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" :aria-label="locale.changePhoto" tabindex="-1" @change="onFile">
            <PhButton variant="secondary" :loading="photoBusy" @click="input?.click()">{{ photoBusy ? locale.uploading : locale.changePhoto }}</PhButton>
            <PhButton v-if="account.avatarUrl" variant="ghost" :disabled="photoBusy" @click="emit('remove')">{{ locale.removePhoto }}</PhButton>
          </div>
          <p class="text-xs text-(--ph-ink-3)">{{ locale.photoHint }}</p>
          <p v-if="errors.photo" role="alert" class="text-xs text-error">{{ errors.photo }}</p>
        </div>
      </div>
      <div class="grid gap-4 md:grid-cols-2">
        <PhInput v-model="form.name" :label="locale.name" :error="errors.name" required />
        <PhInput :model-value="account.email" type="email" :label="locale.email" :hint="locale.emailHint" disabled />
        <PhSelect v-model="form.locale" :label="locale.language" :options="languages" :error="errors.locale" />
      </div>
      <p v-if="account.providers.includes('github')" class="text-sm text-(--ph-ink-2)">{{ locale.githubLinked }}</p>
    </div>
  </PhCard>
</template>
