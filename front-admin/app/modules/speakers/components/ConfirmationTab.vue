<script setup lang="ts">
import { ref } from 'vue'
import locale from '../locales/pt-br'
import { dateTime } from '../composables/format'
import { STATUS_TONE } from './statusTone'
import type { SpeakerTalk } from '../types'

const props = defineProps<{
  talks: SpeakerTalk[]
  disabled: boolean
  extend: (proposalId: string, deadlineAt: string) => Promise<{ ok: boolean, message?: string }>
  remind: (talk: SpeakerTalk) => void
}>()
const emit = defineEmits<{ extended: [deadline: string] }>()

const dates = ref<Record<string, string>>({})
const errors = ref<Record<string, string | null>>({})
const busy = ref<string | null>(null)

async function submitExtension(talk: SpeakerTalk) {
  const day = dates.value[talk.id]
  if (!day) return
  const deadline = new Date(`${day}T23:59:59-03:00`).toISOString()
  busy.value = talk.id
  const result = await props.extend(talk.id, deadline)
  busy.value = null
  errors.value = { ...errors.value, [talk.id]: result.ok ? null : (result.message ?? null) }
  if (result.ok) emit('extended', deadline)
}
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-5">
      <h2 class="text-lg font-semibold">{{ locale.confirmationTitle }}</h2>
      <article v-for="talk in talks" :key="talk.id" class="flex flex-col gap-3 border-t border-(--ph-line) pt-4 first:border-t-0 first:pt-0">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <b class="font-medium">#{{ talk.number }} {{ talk.title }}</b>
          <PhBadge :status="STATUS_TONE[talk.status]">{{ talk.statusLabel }}</PhBadge>
        </div>
        <p v-if="talk.status === 'waitlisted'" class="text-sm text-(--ph-ink-2)">{{ locale.waitlistNote }}</p>
        <dl v-else class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[12rem_1fr]">
          <dt class="font-medium">{{ locale.deadline }}</dt>
          <dd>{{ dateTime(talk.deadlineAt) ?? locale.never }}</dd>
          <dt class="font-medium">{{ locale.respondedAt }}</dt>
          <dd>{{ dateTime(talk.respondedAt) ?? locale.never }}</dd>
          <dt class="font-medium">{{ locale.remindedAt }}</dt>
          <dd>{{ dateTime(talk.remindedAt) ?? locale.never }}</dd>
          <template v-if="talk.reason">
            <dt class="font-medium">{{ locale.reason }}</dt>
            <dd>{{ talk.reason }}</dd>
          </template>
          <template v-if="talk.attendedAt">
            <dt class="font-medium">{{ locale.attendedAt }}</dt>
            <dd>{{ dateTime(talk.attendedAt) }}</dd>
          </template>
        </dl>
        <div v-if="!disabled && ['pending', 'no_response'].includes(talk.status)" class="flex flex-wrap items-end gap-3">
          <PhInput v-model="dates[talk.id]" type="date" :label="locale.newDeadline" :error="errors[talk.id]" />
          <PhButton variant="secondary" :loading="busy === talk.id" :disabled="!dates[talk.id]" @click="submitExtension(talk)">{{ locale.extend }}</PhButton>
          <PhButton v-if="talk.status === 'pending'" variant="ghost" @click="remind(talk)">{{ locale.remindOne }}</PhButton>
        </div>
      </article>
    </div>
  </PhCard>
</template>
