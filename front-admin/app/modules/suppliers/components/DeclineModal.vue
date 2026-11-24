<script setup lang="ts">
import { ref, watch } from 'vue'
import locale from '../locales/pt-br'
import type { MutationResult } from '../types'

const props = defineProps<{ name: string, decline: (reason: string) => Promise<MutationResult> }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [] }>()

const reason = ref('')
const error = ref<string | null>(null)
const busy = ref(false)

watch(open, (value) => {
  if (!value) return
  reason.value = ''
  error.value = null
})

async function submit() {
  if (reason.value.trim() === '') {
    error.value = locale.required
    return
  }
  busy.value = true
  const result = await props.decline(reason.value)
  busy.value = false
  if (result.ok) {
    emit('saved')
    open.value = false
    return
  }
  error.value = result.fieldErrors.reason ?? result.message
}
</script>

<template>
  <PhModal v-model="open" :title="locale.declineTitle.replace('{nome}', name)">
    <form id="decline-form" novalidate @submit.prevent="submit">
      <PhTextarea id="decline-reason" v-model="reason" :label="locale.declineReason" :hint="locale.declineReasonHint" required :rows="3" :error="error" />
    </form>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
      <PhButton type="submit" form="decline-form" variant="danger" :loading="busy">{{ locale.decline }}</PhButton>
    </template>
  </PhModal>
</template>
