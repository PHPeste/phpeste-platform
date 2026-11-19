<script setup lang="ts">
import { ref, watch } from 'vue'
import locale from '../locales/pt-br'
import type { MutationResult, Order } from '../types'

const props = defineProps<{ order: Order, markPaid: (justification: string) => Promise<MutationResult> }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [] }>()

const justification = ref('')
const error = ref<string | null>(null)
const message = ref<string | null>(null)
const busy = ref(false)

watch(open, (value) => {
  if (!value) return
  justification.value = ''
  error.value = null
  message.value = null
})

async function submit() {
  if (justification.value.trim().length < 10) {
    error.value = locale.justificationShort
    return
  }
  busy.value = true
  const result = await props.markPaid(justification.value)
  busy.value = false
  if (result.ok) {
    emit('saved')
    open.value = false
    return
  }
  error.value = result.fieldErrors.justification ?? null
  message.value = error.value ? null : result.message
}
</script>

<template>
  <PhModal v-model="open" :title="locale.markPaidTitle.replace('{n}', String(order.number))">
    <form id="mark-paid-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <p class="text-sm text-(--ph-ink-2)">{{ locale.markPaidText }}</p>
      <PhTextarea id="mark-paid-justification" v-model="justification" :label="locale.justification" :hint="locale.justificationHint" required :rows="3" :error="error" />
    </form>
    <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
      <PhButton type="submit" form="mark-paid-form" :loading="busy">{{ locale.markPaidConfirm }}</PhButton>
    </template>
  </PhModal>
</template>
