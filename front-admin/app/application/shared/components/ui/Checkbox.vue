<script setup lang="ts">
import { computed, useId } from 'vue'

const props = withDefaults(
  defineProps<{
    label: string
    hint?: string
    disabled?: boolean
    id?: string
  }>(),
  { hint: undefined, disabled: false, id: undefined },
)

const model = defineModel<boolean>({ default: false })

const autoId = useId()
const fieldId = computed(() => props.id ?? `ph-${autoId}`)
const hintId = computed(() => `${fieldId.value}-hint`)
</script>

<template>
  <div class="flex min-h-11 items-start gap-3 py-2.5">
    <input
      :id="fieldId"
      v-model="model"
      type="checkbox"
      class="checkbox checkbox-primary mt-0.5"
      :disabled="disabled"
      :aria-describedby="hint ? hintId : undefined"
    >
    <div class="flex flex-col">
      <label :for="fieldId" class="cursor-pointer text-sm">{{ label }}</label>
      <p v-if="hint" :id="hintId" class="text-xs text-(--ph-ink-3)">{{ hint }}</p>
    </div>
  </div>
</template>
