<script setup lang="ts">
import locale from '../locales/pt-br'
import type { PrivacyInput } from '../types'

defineProps<{ errors: Record<string, string>, disabled: boolean }>()
const form = defineModel<PrivacyInput>({ required: true })
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-4">
      <h2 class="text-lg font-semibold">{{ locale.privacyTitle }}</h2>
      <p class="text-sm text-(--ph-ink-2)">{{ locale.privacyIntro }}</p>
      <div class="grid gap-4 md:grid-cols-2">
        <PhInput id="settings-privacyContactEmail" v-model="form.privacyContactEmail" type="email" :label="locale.privacyEmail" :hint="locale.privacyEmailHint" :error="errors.privacyContactEmail" :disabled="disabled" />
        <PhInput id="settings-privacyPolicyUrl" v-model="form.privacyPolicyUrl" type="url" :label="locale.privacyPolicy" placeholder="https://" :error="errors.privacyPolicyUrl" :disabled="disabled" />
      </div>
      <PhCheckbox v-model="form.anonymizeAfterEvent" :label="locale.anonymize" :hint="locale.anonymizeHint" :disabled="disabled" />
      <p class="text-sm text-(--ph-ink-2)">{{ locale.privacySoon }}</p>
      <p class="text-xs text-(--ph-ink-3)">{{ locale.privacyLegal }}</p>
    </div>
  </PhCard>
</template>
