<script setup lang="ts">
import { ref } from 'vue'
import locale from '../locales/pt-br'
import type { LogoSlot } from '../types'

const SLOTS: LogoSlot[] = ['primary', 'dark', 'favicon', 'app_icon']

defineProps<{ logos: Record<LogoSlot, string | null>, uploading: LogoSlot | null, disabled?: boolean }>()
const emit = defineEmits<{ upload: [slot: LogoSlot, file: File], remove: [slot: LogoSlot] }>()

const inputs = ref<Record<string, HTMLInputElement | null>>({})

function onFile(slot: LogoSlot, event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) emit('upload', slot, file)
  input.value = ''
}
</script>

<template>
  <div class="grid gap-4 sm:grid-cols-2">
    <div v-for="slot in SLOTS" :key="slot" class="flex flex-col gap-1.5">
      <span :id="`logo-${slot}-label`" class="text-sm font-medium">{{ locale.logos[slot] }}</span>
      <div
        class="flex min-h-24 items-center justify-center rounded-(--ph-radius) border border-dashed border-(--ph-line) p-3"
        :class="slot === 'dark' ? 'bg-(--ph-ink)' : 'bg-(--ph-surface)'"
      >
        <img v-if="logos[slot]" :src="logos[slot] ?? undefined" :alt="locale.logos[slot]" class="max-h-16 max-w-full object-contain">
        <span v-else class="text-center text-xs" :class="slot === 'dark' ? 'text-(--ph-side-text)' : 'text-(--ph-ink-3)'">{{ locale.logoHints[slot] }}</span>
      </div>
      <div class="flex flex-wrap gap-2">
        <input
          :ref="(el) => { inputs[slot] = el as HTMLInputElement | null }"
          type="file"
          class="sr-only"
          :accept="slot === 'app_icon' ? 'image/png' : 'image/png,image/svg+xml'"
          :aria-labelledby="`logo-${slot}-label`"
          :disabled="disabled"
          tabindex="-1"
          @change="onFile(slot, $event)"
        >
        <PhButton variant="ghost" :loading="uploading === slot" :disabled="disabled" @click="inputs[slot]?.click()">
          {{ uploading === slot ? locale.uploading : logos[slot] ? locale.replace : locale.upload }}
          <span class="sr-only">{{ locale.logos[slot] }}</span>
        </PhButton>
        <PhButton v-if="logos[slot]" variant="ghost" :disabled="disabled" @click="emit('remove', slot)">
          {{ locale.remove }}<span class="sr-only"> {{ locale.logos[slot] }}</span>
        </PhButton>
      </div>
    </div>
  </div>
</template>
