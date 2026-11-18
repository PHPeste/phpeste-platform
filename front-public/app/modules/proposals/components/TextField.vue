<script setup lang="ts">
import { computed, useId } from 'vue'

const props = withDefaults(defineProps<{ label: string, type?: string, hint?: string, error?: string | null, required?: boolean, maxlength?: number, autocomplete?: string }>(), {
  type: 'text', hint: undefined, error: null, required: false, maxlength: undefined, autocomplete: undefined,
})
const model = defineModel<string>({ default: '' })
const id = `f-${useId()}`
const describedBy = computed(() => [props.error ? `${id}-error` : null, props.hint ? `${id}-hint` : null].filter(Boolean).join(' ') || undefined)
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label :for="id" class="text-sm font-medium">{{ label }}<span v-if="required" class="text-(--ph-ink-3)"> *</span></label>
    <input
      :id="id"
      v-model="model"
      :type="type"
      class="input w-full"
      :class="{ 'input-error': error }"
      :maxlength="maxlength"
      :autocomplete="autocomplete"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="describedBy"
      :required="required"
    >
    <p v-if="hint" :id="`${id}-hint`" class="text-xs text-(--ph-ink-3)">{{ hint }}</p>
    <p v-if="error" :id="`${id}-error`" role="alert" class="text-xs text-error">{{ error }}</p>
  </div>
</template>
