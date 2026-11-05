<script setup lang="ts">
import { computed, useId } from 'vue'
import locale from '@/application/locales/pt-br/ui'

export interface SelectOption {
  value: string
  label: string
}

const props = withDefaults(
  defineProps<{
    label: string
    options: SelectOption[]
    placeholder?: string
    hint?: string
    error?: string | null
    disabled?: boolean
    required?: boolean
    id?: string
  }>(),
  { placeholder: undefined, hint: undefined, error: null, disabled: false, required: false, id: undefined },
)

const model = defineModel<string>({ default: '' })

const autoId = useId()
const fieldId = computed(() => props.id ?? `ph-${autoId}`)
const hintId = computed(() => `${fieldId.value}-hint`)
const errorId = computed(() => `${fieldId.value}-error`)
const describedBy = computed(() => {
  if (props.error) return errorId.value
  return props.hint ? hintId.value : undefined
})
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label :for="fieldId" class="text-sm font-medium">
      {{ label }}
      <span v-if="required" class="text-(--ph-ink-3) font-normal">({{ locale.required }})</span>
    </label>
    <select
      :id="fieldId"
      v-model="model"
      :disabled="disabled"
      :required="required"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="describedBy"
      class="select w-full"
      :class="{ 'select-error': error }"
    >
      <option v-if="placeholder !== undefined" value="" disabled>{{ placeholder }}</option>
      <option v-for="option in options" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>
    <p v-if="error" :id="errorId" role="alert" class="text-sm text-error">{{ error }}</p>
    <p v-else-if="hint" :id="hintId" class="text-xs text-(--ph-ink-3)">{{ hint }}</p>
  </div>
</template>
