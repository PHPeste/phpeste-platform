<script setup lang="ts">
import { computed } from 'vue'
import locale from '../locales/pt-br'
import { longDay } from '../composables/dates'
import type { CfpGeneral, ProposalFormOptions, Track } from '../types'

const props = defineProps<{ general: CfpGeneral, status: string, tracks: Track[], form: ProposalFormOptions }>()

const options = (items: Array<{ id: string, label: string }>) => items.map((item) => ({ value: item.id, label: item.label }))
const trackOptions = computed(() => (props.tracks.length
  ? props.tracks.map((track) => ({ value: track.id, label: track.name }))
  : [{ value: '', label: locale.noTrackOption }]))
const max = (n: number) => locale.maxChars.replace('{n}', n.toLocaleString('pt-BR'))
const perPerson = computed(() => (props.general.proposalsPerPerson === 1 ? locale.formPerPersonOne : locale.formPerPerson.replace('{n}', String(props.general.proposalsPerPerson))))
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-5">
      <div class="flex flex-col gap-1">
        <h2 class="text-lg font-semibold">{{ locale.formTitle }}</h2>
        <p class="text-sm text-(--ph-ink-2)">{{ locale.formHint }}</p>
      </div>
      <div data-testid="form-preview" class="flex flex-col gap-4 rounded-xl border border-(--ph-line) bg-(--ph-bg) p-5" inert>
        <ul class="flex flex-col gap-1 text-sm text-(--ph-ink-2)">
          <li v-if="general.closesOn">{{ locale.formDeadline.replace('{data}', longDay(general.closesOn)) }}</li>
          <li>{{ perPerson }}</li>
          <li v-if="general.anonymousReview">{{ locale.formAnonymous }}</li>
          <li v-if="status !== 'open'" class="font-medium text-(--ph-warn)">{{ locale.formClosed }}</li>
        </ul>
        <PhInput :label="locale.fieldTitle" :hint="max(form.limits.title)" required />
        <PhSelect :label="locale.fieldTrack" :options="trackOptions" :placeholder="locale.chooseTrack" required />
        <div class="grid gap-4 sm:grid-cols-3">
          <PhSelect :label="locale.fieldFormat" :options="options(form.formats)" :placeholder="locale.chooseFormat" required />
          <PhSelect :label="locale.fieldLevel" :options="options(form.levels)" :placeholder="locale.chooseLevel" required />
          <PhSelect :label="locale.fieldLanguage" :model-value="form.languages[0]?.id ?? ''" :options="options(form.languages)" />
        </div>
        <PhTextarea :label="locale.fieldSummary" :hint="max(form.limits.summary)" required />
        <PhTextarea :label="locale.fieldAudience" :hint="max(form.limits.audience)" :rows="2" />
        <PhButton class="self-start">{{ locale.submitExample }}</PhButton>
      </div>
    </div>
  </PhCard>
</template>
