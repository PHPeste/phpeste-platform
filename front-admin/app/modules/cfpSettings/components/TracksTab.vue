<script setup lang="ts">
import { ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import TrackModal from './TrackModal.vue'
import locale from '../locales/pt-br'
import type { MutationResult, Track, TrackInput } from '../types'

const props = defineProps<{
  tracks: Track[]
  disabled: boolean
  save: (track: Track | null, input: TrackInput) => Promise<MutationResult>
  remove: (track: Track) => Promise<MutationResult>
  move: (track: Track, direction: -1 | 1) => Promise<MutationResult>
}>()

const toast = useToast()
const editing = ref<Track | null>(null)
const open = ref(false)

function edit(track: Track | null) {
  editing.value = track
  open.value = true
}

async function reorder(track: Track, direction: -1 | 1) {
  const result = await props.move(track, direction)
  if (!result.ok && 'message' in result) toast.error(result.message)
}
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-3">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">{{ locale.tracksTitle }}</h2>
        <PhButton v-if="!disabled" @click="edit(null)">{{ locale.newTrack }}</PhButton>
      </div>
      <p v-if="!tracks.length" class="text-sm text-(--ph-ink-2)">{{ locale.noTracks }}</p>
      <PhTable v-else :caption="locale.tracksTitle">
        <thead>
          <tr>
            <th scope="col">{{ locale.track }}</th>
            <th scope="col" class="text-right">{{ locale.proposals }}</th>
            <th scope="col" class="text-right">{{ locale.slots }}</th>
            <th scope="col"><span class="sr-only">{{ locale.actions }}</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(track, index) in tracks" :key="track.id">
            <td><b class="font-medium">{{ track.name }}</b></td>
            <td class="text-right tabular-nums">{{ track.proposals }}</td>
            <td class="text-right tabular-nums">{{ track.slots }}</td>
            <td class="text-right">
              <div v-if="!disabled" class="flex justify-end gap-1">
                <PhButton variant="ghost" :disabled="index === 0" :aria-label="locale.moveUp.replace('{nome}', track.name)" @click="reorder(track, -1)">↑</PhButton>
                <PhButton variant="ghost" :disabled="index === tracks.length - 1" :aria-label="locale.moveDown.replace('{nome}', track.name)" @click="reorder(track, 1)">↓</PhButton>
                <PhButton variant="ghost" :aria-label="`${locale.edit} ${track.name}`" @click="edit(track)">{{ locale.edit }}</PhButton>
              </div>
            </td>
          </tr>
        </tbody>
      </PhTable>
    </div>
    <TrackModal
      v-model="open"
      :track="editing"
      :save="save"
      :remove="remove"
      @saved="toast.success(locale.trackSaved.replace('{nome}', $event))"
      @removed="toast.success(locale.trackRemoved.replace('{nome}', $event))"
    />
  </PhCard>
</template>
