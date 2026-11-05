<script setup lang="ts">
import { computed, useId } from 'vue'
import locale from '@/application/locales/pt-br/ui'

const props = withDefaults(
  defineProps<{
    label: string
    type?: 'text' | 'email' | 'password' | 'number' | 'date' | 'time' | 'datetime-local' | 'search' | 'tel' | 'url'
    placeholder?: string
    hint?: string
    error?: string | null
    disabled?: boolean
    required?: boolean
    autocomplete?: string
    id?: string
  }>(),
  { type: 'text', placeholder: undefined, hint: undefined, error: null, disabled: false, required: false, autocomplete: undefined, id: undefined },
)

const model = defineModel<string | number>({ default: '' })

const autoId = useId()
const inputId = computed(() => props.id ?? `ph-${autoId}`)
const hintId = computed(() => `${inputId.value}-hint`)
const errorId = computed(() => `${inputId.value}-error`)
const describedBy = computed(() => {
  if (props.error) return errorId.value
  return props.hint ? hintId.value : undefined
})
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label :for="inputId" class="text-sm font-medium">
      {{ label }}
      <span v-if="required" class="text-(--ph-ink-3) font-normal">({{ locale.required }})</span>
    </label>
    <input
      :id="inputId"
      v-model="model"
      :type="type"
      :placeholder="placeholder"
      :disabled="disabled"
      :required="required"
      :autocomplete="autocomplete"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="describedBy"
      class="input w-full"
      :class="{ 'input-error': error }"
    >
    <p v-if="error" :id="errorId" role="alert" class="text-sm text-error">{{ error }}</p>
    <p v-else-if="hint" :id="hintId" class="text-xs text-(--ph-ink-3)">{{ hint }}</p>
  </div>
</template>
