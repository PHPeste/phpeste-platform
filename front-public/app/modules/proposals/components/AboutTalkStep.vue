<script setup lang="ts">
import SelectField from './SelectField.vue'
import TextAreaField from './TextAreaField.vue'
import TextField from '@/application/shared/components/TextField.vue'
import locale from '../locales/pt-br'
import type { CfpInfo, TalkForm } from '../types'

defineProps<{ cfp: CfpInfo, errors: Record<string, string> }>()
const talk = defineModel<TalkForm>({ required: true })
</script>

<template>
  <div class="flex flex-col gap-5">
    <TextField v-model="talk.title" :label="locale.talkTitle" :hint="locale.maxChars.replace('{n}', String(cfp.limits.title))" :maxlength="cfp.limits.title" required :error="errors.title" />
    <SelectField v-model="talk.trackId" :label="locale.track" :options="cfp.tracks" :placeholder="locale.chooseTrack" required :error="errors.trackId" />
    <div class="grid gap-4 sm:grid-cols-3">
      <SelectField v-model="talk.format" :label="locale.format" :options="cfp.formats" :placeholder="locale.chooseFormat" required :error="errors.format" />
      <SelectField v-model="talk.level" :label="locale.level" :options="cfp.levels" :placeholder="locale.chooseLevel" required :error="errors.level" />
      <SelectField v-model="talk.language" :label="locale.language" :options="cfp.languages" :error="errors.language" />
    </div>
    <TextAreaField v-model="talk.summary" :label="locale.summary" :hint="locale.summaryHint" :maxlength="cfp.limits.summary" :rows="6" required :error="errors.summary" />
    <TextAreaField v-model="talk.audience" :label="locale.audience" :maxlength="cfp.limits.audience" :rows="3" required :error="errors.audience" />
  </div>
</template>
