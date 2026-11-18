<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import locale from '../locales/pt-br'
import type { InviteInput, InviteOptions } from '../types'

type InviteResult = { ok: true, number: number } | { ok: false, message: string, fieldErrors: Record<string, string> }

const props = defineProps<{ loadOptions: () => Promise<InviteOptions>, send: (input: InviteInput) => Promise<InviteResult> }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ invited: [number: number] }>()

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const form = reactive<InviteInput>({ name: '', email: '', title: '', trackId: '', format: '', summary: '' })
const options = ref<InviteOptions | null>(null)
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)
const busy = ref(false)

watch(open, async (value) => {
  if (!value) return
  Object.assign(form, { name: '', email: '', title: '', trackId: '', format: '', summary: '' })
  errors.value = {}
  message.value = null
  options.value = null
  try {
    options.value = await props.loadOptions()
    form.format = options.value.formats[0]?.value ?? ''
    form.trackId = options.value.tracks[0]?.value ?? ''
  } catch {
    message.value = locale.genericError
  }
})

async function submit() {
  errors.value = {}
  if (!form.name.trim()) errors.value.name = locale.required
  if (!EMAIL.test(form.email.trim())) errors.value.email = locale.required
  if (!form.title.trim()) errors.value.title = locale.required
  if (Object.keys(errors.value).length) return

  busy.value = true
  const result = await props.send({ ...form })
  busy.value = false
  if (result.ok) {
    emit('invited', result.number)
    open.value = false
    return
  }
  errors.value = result.fieldErrors
  message.value = Object.keys(result.fieldErrors).length ? null : result.message
}
</script>

<template>
  <PhModal v-model="open" :title="locale.inviteTitle">
    <PhSkeleton v-if="!options && !message" :lines="5" />
    <form v-else-if="options" id="invite-speaker-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <p class="text-sm text-(--ph-ink-2)">{{ locale.inviteHint }}</p>
      <p v-if="!options.tracks.length" role="alert" class="alert alert-warning alert-soft text-sm">{{ locale.noTracksToInvite }}</p>
      <PhInput v-model="form.name" :label="locale.inviteName" required :error="errors.name" />
      <PhInput v-model="form.email" type="email" :label="locale.inviteEmail" :hint="locale.inviteEmailHint" required :error="errors.email" />
      <PhInput v-model="form.title" :label="locale.inviteTalk" required :error="errors.title" />
      <div class="grid gap-4 sm:grid-cols-2">
        <PhSelect v-model="form.trackId" :label="locale.inviteTrack" :options="options.tracks" required :error="errors.trackId" />
        <PhSelect v-model="form.format" :label="locale.inviteFormat" :options="options.formats" required :error="errors.format" />
      </div>
      <PhTextarea v-model="form.summary" :label="locale.inviteSummary" :rows="3" :error="errors.summary" />
    </form>
    <p v-if="message" role="alert" class="alert alert-error alert-soft mt-4 text-sm">{{ message }}</p>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.cancel }}</PhButton>
      <PhButton type="submit" form="invite-speaker-form" :loading="busy" :disabled="!options?.tracks.length">{{ locale.inviteSend }}</PhButton>
    </template>
  </PhModal>
</template>
