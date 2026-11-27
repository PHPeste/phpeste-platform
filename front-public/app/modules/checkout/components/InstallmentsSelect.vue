<script setup lang="ts">
import { computed } from 'vue'
import { formatCents } from '../composables/format'
import locale from '../locales/pt-br'

const props = defineProps<{ id: string, options: Array<{ installments: number, amountCents: number }>, note?: boolean }>()
const model = defineModel<number>({ default: 1 })
const label = (option: { installments: number, amountCents: number }) => (option.installments === 1
  ? locale.installmentOne.replace('{valor}', formatCents(option.amountCents))
  : locale.installmentOption.replace('{n}', String(option.installments)).replace('{valor}', formatCents(option.amountCents)))
const describedBy = computed(() => (props.note ? `${props.id}-note` : undefined))
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label :for="id" class="text-sm font-medium">{{ locale.installments }}</label>
    <select :id="id" v-model.number="model" class="select w-full" :aria-describedby="describedBy">
      <option v-for="option in options" :key="option.installments" :value="option.installments">{{ label(option) }}</option>
    </select>
    <p v-if="note" :id="`${id}-note`" class="text-xs text-(--ph-ink-3)">{{ locale.installmentsNote }}</p>
  </div>
</template>
