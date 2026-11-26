<script setup lang="ts">
import { computed } from 'vue'
import locale from '../locales/pt-br'

const props = withDefaults(defineProps<{ id: string, label: string, type?: string, hint?: string, error?: string | null, required?: boolean, autocomplete?: string, inputmode?: 'text' | 'email' | 'numeric' }>(), {
  type: 'text', hint: undefined, error: null, required: false, autocomplete: undefined, inputmode: undefined,
})
const model = defineModel<string>({ default: '' })
const describedBy = computed(() => [props.error ? `${props.id}-error` : null, props.hint ? `${props.id}-hint` : null].filter(Boolean).join(' ') || undefined)
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label :for="id" class="text-sm font-medium">{{ label }} <span v-if="required" class="font-normal text-(--ph-ink-3)">{{ locale.required }}</span></label>
    <input
      :id="id"
      v-model="model"
      :type="type"
      class="input w-full"
      :class="{ 'input-error': error }"
      :autocomplete="autocomplete"
      :inputmode="inputmode"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="describedBy"
      :aria-required="required ? 'true' : undefined"
    >
    <p v-if="hint" :id="`${id}-hint`" class="text-xs text-(--ph-ink-3)">{{ hint }}</p>
    <p v-if="error" :id="`${id}-error`" class="text-xs text-error">{{ error }}</p>
  </div>
</template>
