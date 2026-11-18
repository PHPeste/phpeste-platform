<script setup lang="ts">
import { computed, useId } from 'vue'
import { HEX } from '../composables/contrast'
import locale from '../locales/pt-br'

const props = defineProps<{ label: string, disabled?: boolean }>()
const model = defineModel<string>({ required: true })

const id = `color-${useId()}`
const invalid = computed(() => !HEX.test(model.value))
const pickerValue = computed(() => (invalid.value ? '#000000' : model.value.toLowerCase()))

function onText(value: string) {
  const trimmed = value.trim()
  model.value = trimmed.startsWith('#') ? trimmed : `#${trimmed}`
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-3">
    <input
      type="color"
      :value="pickerValue"
      :disabled="disabled"
      :aria-label="locale.colorPicker.replace('{token}', props.label)"
      class="size-9 shrink-0 cursor-pointer rounded-full border border-(--ph-line) bg-transparent p-0"
      @input="model = ($event.target as HTMLInputElement).value.toUpperCase()"
    >
    <label :for="id" class="min-w-32 flex-1 text-sm">{{ label }}</label>
    <div class="flex flex-col gap-1">
      <input
        :id="id"
        :value="model"
        :disabled="disabled"
        maxlength="7"
        spellcheck="false"
        :aria-invalid="invalid ? 'true' : undefined"
        :aria-describedby="invalid ? `${id}-error` : undefined"
        class="input input-sm w-28 font-mono uppercase"
        :class="{ 'input-error': invalid }"
        @input="onText(($event.target as HTMLInputElement).value)"
      >
      <p v-if="invalid" :id="`${id}-error`" role="alert" class="text-xs text-error">{{ locale.hexInvalid }}</p>
    </div>
  </div>
</template>
