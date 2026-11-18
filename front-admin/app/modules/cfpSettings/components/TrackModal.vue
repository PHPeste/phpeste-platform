<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import locale from '../locales/pt-br'
import type { MutationResult, Track, TrackInput } from '../types'

const props = defineProps<{ track: Track | null, save: (track: Track | null, input: TrackInput) => Promise<MutationResult>, remove: (track: Track) => Promise<MutationResult> }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [name: string], removed: [name: string] }>()

const name = ref('')
const slots = ref(0)
const confirming = ref(false)
const busy = ref(false)
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)

watch(open, (value) => {
  if (!value) return
  name.value = props.track?.name ?? ''
  slots.value = props.track?.slots ?? 4
  confirming.value = false
  errors.value = {}
  message.value = null
})

const title = computed(() => {
  if (confirming.value && props.track) return locale.removeTrackTitle.replace('{nome}', props.track.name)
  return props.track ? locale.editTrack : locale.newTrack
})
const removeText = computed(() => {
  const n = props.track?.proposals ?? 0
  if (n === 0) return locale.removeTrackEmpty
  return n === 1 ? locale.removeTrackWithOne : locale.removeTrackWithProposals.replace('{n}', String(n))
})

function apply(result: MutationResult, done: () => void) {
  if (result.ok) return done()
  if ('message' in result) {
    errors.value = result.fieldErrors
    message.value = Object.keys(result.fieldErrors).length ? null : result.message
  }
}

async function submit() {
  errors.value = name.value.trim() ? {} : { name: locale.required }
  if (errors.value.name) return
  busy.value = true
  const result = await props.save(props.track, { name: name.value.trim(), slots: Number(slots.value) })
  busy.value = false
  apply(result, () => {
    emit('saved', name.value.trim())
    open.value = false
  })
}

async function confirmRemove() {
  if (!props.track) return
  busy.value = true
  const result = await props.remove(props.track)
  busy.value = false
  apply(result, () => {
    emit('removed', props.track!.name)
    open.value = false
  })
}
</script>

<template>
  <PhModal v-model="open" :title="title">
    <form v-if="!confirming" id="track-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <PhInput v-model="name" :label="locale.trackName" required :error="errors.name" />
      <PhInput v-model.number="slots" type="number" :label="locale.slots" :error="errors.slots" />
      <div v-if="track" class="border-t border-(--ph-line) pt-4">
        <PhButton variant="ghost" class="text-error" @click="confirming = true">{{ locale.removeTrack }}</PhButton>
      </div>
    </form>
    <p v-else>{{ removeText }}</p>
    <p v-if="message" role="alert" class="alert alert-error alert-soft mt-4 text-sm">{{ message }}</p>
    <template #footer>
      <template v-if="!confirming">
        <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
        <PhButton type="submit" form="track-form" :loading="busy">{{ locale.saveTrack }}</PhButton>
      </template>
      <template v-else>
        <PhButton variant="ghost" @click="confirming = false">{{ locale.back }}</PhButton>
        <PhButton variant="danger" :loading="busy" @click="confirmRemove">{{ locale.removeTrack }}</PhButton>
      </template>
    </template>
  </PhModal>
</template>
