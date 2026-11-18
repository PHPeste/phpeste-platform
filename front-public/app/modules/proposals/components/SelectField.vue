<script setup lang="ts">
import { computed, useId } from 'vue'
import type { Option } from '../types'

const props = withDefaults(defineProps<{ label: string, options: Option[], placeholder?: string, hint?: string, error?: string | null, required?: boolean }>(), {
  placeholder: undefined, hint: undefined, error: null, required: false,
})
const model = defineModel<string>({ default: '' })
const id = `f-${useId()}`
const describedBy = computed(() => [props.error ? `${id}-error` : null, props.hint ? `${id}-hint` : null].filter(Boolean).join(' ') || undefined)
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label :for="id" class="text-sm font-medium">{{ label }}<span v-if="required" class="text-(--ph-ink-3)"> *</span></label>
    <select :id="id" v-model="model" class="select w-full" :class="{ 'select-error': error }" :aria-invalid="error ? 'true' : undefined" :aria-describedby="describedBy" :required="required">
      <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
      <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
    </select>
    <p v-if="hint" :id="`${id}-hint`" class="text-xs text-(--ph-ink-3)">{{ hint }}</p>
    <p v-if="error" :id="`${id}-error`" role="alert" class="text-xs text-error">{{ error }}</p>
  </div>
</template>
