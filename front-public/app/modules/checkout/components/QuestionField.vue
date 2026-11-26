<script setup lang="ts">
import { computed } from 'vue'
import CheckoutField from './CheckoutField.vue'
import locale from '../locales/pt-br'
import type { Answer, Question } from '../types'

const props = defineProps<{ id: string, question: Question, error: string | null }>()
const model = defineModel<Answer | undefined>()
const text = computed({ get: () => (typeof model.value === 'string' ? model.value : ''), set: (value: string) => { model.value = value } })
const describedBy = computed(() => (props.error ? `${props.id}-error` : undefined))
</script>

<template>
  <CheckoutField v-if="question.type === 'text'" :id="id" v-model="text" :label="question.label" :required="question.required" :error="error" />

  <div v-else-if="question.type === 'select'" class="flex flex-col gap-1.5">
    <label :for="id" class="text-sm font-medium">{{ question.label }} <span v-if="question.required" class="font-normal text-(--ph-ink-3)">{{ locale.required }}</span></label>
    <select :id="id" v-model="text" class="select w-full" :class="{ 'select-error': error }" :aria-invalid="error ? 'true' : undefined" :aria-describedby="describedBy">
      <option value="" />
      <option v-for="option in question.options" :key="option" :value="option">{{ option }}</option>
    </select>
    <p v-if="error" :id="`${id}-error`" class="text-xs text-error">{{ error }}</p>
  </div>

  <fieldset v-else class="flex flex-col gap-1.5" :aria-describedby="describedBy">
    <legend class="text-sm font-medium">{{ question.label }} <span v-if="question.required" class="font-normal text-(--ph-ink-3)">{{ locale.required }}</span></legend>
    <div class="flex gap-4">
      <label class="flex min-h-11 cursor-pointer items-center gap-2">
        <input :id="id" type="radio" class="radio radio-sm" :name="id" :checked="model === true" :aria-invalid="error ? 'true' : undefined" @change="model = true">
        {{ locale.yes }}
      </label>
      <label class="flex min-h-11 cursor-pointer items-center gap-2">
        <input type="radio" class="radio radio-sm" :name="id" :checked="model === false" @change="model = false">
        {{ locale.no }}
      </label>
    </div>
    <p v-if="error" :id="`${id}-error`" class="text-xs text-error">{{ error }}</p>
  </fieldset>
</template>
