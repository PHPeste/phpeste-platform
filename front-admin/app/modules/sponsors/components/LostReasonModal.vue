<script setup lang="ts">
import { ref, watch } from 'vue'
import locale from '../locales/pt-br'

defineProps<{ company: string, busy: boolean }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ confirm: [reason: string] }>()

const reason = ref('')
const error = ref<string | null>(null)

watch(open, (value) => {
  if (!value) return
  reason.value = ''
  error.value = null
})

function submit() {
  if (reason.value.trim() === '') {
    error.value = locale.required
    return
  }
  emit('confirm', reason.value)
}
</script>

<template>
  <PhModal v-model="open" :title="locale.lostModalTitle.replace('{empresa}', company)">
    <form id="lost-form" novalidate @submit.prevent="submit">
      <PhTextarea id="lost-reason" v-model="reason" :label="locale.lostReason" :hint="locale.lostReasonHint" required :rows="3" :error="error" />
    </form>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
      <PhButton type="submit" form="lost-form" variant="danger" :loading="busy">{{ locale.lostConfirm }}</PhButton>
    </template>
  </PhModal>
</template>
