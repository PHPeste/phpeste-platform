<script setup lang="ts">
import { ref } from 'vue'
import locale from '../locales/pt-br'
import type { Preset } from '../types'

defineProps<{ presets: Preset[], current: Record<string, string>, disabled?: boolean, busy?: boolean }>()
const emit = defineEmits<{ apply: [presetId: string] }>()

const pending = ref<Preset | null>(null)
const open = ref(false)

function choose(preset: Preset) {
  pending.value = preset
  open.value = true
}

function confirm() {
  if (pending.value) emit('apply', pending.value.id)
  open.value = false
}

const matches = (preset: Preset, colors: Record<string, string>) =>
  Object.entries(preset.colors).every(([token, value]) => colors[token]?.toUpperCase() === value.toUpperCase())
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-3">
      <h2 class="text-lg font-semibold">{{ locale.presetsTitle }}</h2>
      <ul class="flex flex-col gap-2">
        <li v-for="preset in presets" :key="preset.id">
          <button
            type="button"
            class="flex min-h-14 w-full items-center gap-3 rounded-(--ph-radius) border bg-(--ph-surface) px-3.5 py-2.5 text-left disabled:cursor-not-allowed"
            :class="matches(preset, current) ? 'border-(--ph-brand) ring-2 ring-(--ph-accent)' : 'border-(--ph-line)'"
            :aria-pressed="matches(preset, current)"
            :disabled="disabled || busy"
            @click="choose(preset)"
          >
            <span class="flex" aria-hidden="true">
              <span class="size-5 rounded-full border border-black/15" :style="{ background: preset.colors.brand }" />
              <span class="-ml-2 size-5 rounded-full border border-black/15" :style="{ background: preset.colors.bg }" />
            </span>
            <span class="flex flex-col">
              <b class="font-medium">{{ preset.label }}</b>
              <span class="text-xs text-(--ph-ink-3)">{{ preset.description }}</span>
            </span>
          </button>
        </li>
      </ul>
      <p class="text-xs text-(--ph-ink-3)">{{ locale.presetsHint }}</p>
    </div>

    <PhModal v-model="open" :title="pending?.label ?? ''">
      <p>{{ locale.applyPresetConfirm.replace('{preset}', pending?.label ?? '') }}</p>
      <template #footer>
        <PhButton variant="ghost" @click="open = false">{{ locale.cancel }}</PhButton>
        <PhButton @click="confirm">{{ locale.apply }}</PhButton>
      </template>
    </PhModal>
  </PhCard>
</template>
