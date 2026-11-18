<script setup lang="ts">
import { computed, useId } from 'vue'

const props = withDefaults(defineProps<{ label: string, hint?: string, error?: string | null, required?: boolean, maxlength?: number, rows?: number }>(), {
  hint: undefined, error: null, required: false, maxlength: undefined, rows: 4,
})
const model = defineModel<string>({ default: '' })
const id = `f-${useId()}`
const describedBy = computed(() => [props.error ? `${id}-error` : null, props.hint ? `${id}-hint` : null].filter(Boolean).join(' ') || undefined)
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label :for="id" class="text-sm font-medium">{{ label }}<span v-if="required" class="text-(--ph-ink-3)"> *</span></label>
    <textarea
      :id="id"
      v-model="model"
      class="textarea w-full"
      :class="{ 'textarea-error': error }"
      :rows="rows"
      :maxlength="maxlength"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="describedBy"
      :required="required"
    />
    <div class="flex justify-between gap-3 text-xs text-(--ph-ink-3)">
      <p :id="`${id}-hint`">{{ hint }}</p>
      <span v-if="maxlength" class="tabular-nums" aria-hidden="true">{{ model.length }}/{{ maxlength }}</span>
    </div>
    <p v-if="error" :id="`${id}-error`" role="alert" class="text-xs text-error">{{ error }}</p>
  </div>
</template>
