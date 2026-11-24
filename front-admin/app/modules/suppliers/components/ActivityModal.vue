<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import locale from '../locales/pt-br'
import type { Channel, MutationResult } from '../types'

const props = defineProps<{ log: (channel: Channel, happenedAt: string, summary: string) => Promise<MutationResult> }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [] }>()

const form = reactive({ channel: 'email', happenedAt: '', summary: '' })
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)
const busy = ref(false)
const channels = Object.entries(locale.channels).map(([value, label]) => ({ value, label }))
const labels: Record<string, string> = { channel: locale.channel, happenedAt: locale.happenedAt, summary: locale.summary }
const fieldIds: Record<string, string> = { channel: 'activity-channel', happenedAt: 'activity-at', summary: 'activity-summary' }

watch(open, (value) => {
  if (!value) return
  Object.assign(form, { channel: 'email', happenedAt: '', summary: '' })
  errors.value = {}
  message.value = null
})

async function submit() {
  if (form.summary.trim().length < 2) {
    errors.value = { summary: locale.required }
    return
  }
  busy.value = true
  const result = await props.log(form.channel as Channel, form.happenedAt, form.summary)
  busy.value = false
  if (result.ok) {
    emit('saved')
    open.value = false
    return
  }
  errors.value = result.fieldErrors
  message.value = Object.keys(result.fieldErrors).length ? null : result.message
}
</script>

<template>
  <PhModal v-model="open" :title="locale.activityTitle">
    <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
    <form id="activity-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <div class="grid grid-cols-2 gap-3">
        <PhSelect id="activity-channel" v-model="form.channel" :label="locale.channel" :options="channels" :error="errors.channel" />
        <PhInput id="activity-at" v-model="form.happenedAt" type="datetime-local" :label="locale.happenedAt" :hint="locale.happenedAtHint" :error="errors.happenedAt" />
      </div>
      <PhTextarea id="activity-summary" v-model="form.summary" :label="locale.summary" required :rows="3" :error="errors.summary" />
    </form>
    <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
      <PhButton type="submit" form="activity-form" :loading="busy">{{ locale.logActivity }}</PhButton>
    </template>
  </PhModal>
</template>
