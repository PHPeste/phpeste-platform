<script setup lang="ts">
import { ref, watch } from 'vue'
import { centsToInput, parseReais } from '../composables/format'
import locale from '../locales/pt-br'
import type { MutationResult } from '../types'

const props = defineProps<{ goalCents: number | null, save: (goalCents: number | null) => Promise<MutationResult> }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [] }>()

const goal = ref('')
const error = ref<string | null>(null)
const busy = ref(false)

watch(open, (value) => {
  if (!value) return
  goal.value = props.goalCents === null ? '' : centsToInput(props.goalCents)
  error.value = null
})

async function submit() {
  const cents = parseReais(goal.value)
  if (goal.value.trim() !== '' && cents === null) {
    error.value = locale.goalInvalid
    return
  }
  busy.value = true
  const result = await props.save(cents)
  busy.value = false
  if (result.ok) {
    emit('saved')
    open.value = false
    return
  }
  error.value = result.fieldErrors.goal ?? result.message
}
</script>

<template>
  <PhModal v-model="open" :title="locale.goalTitle">
    <form id="goal-form" novalidate @submit.prevent="submit">
      <PhInput id="goal-value" v-model="goal" :label="locale.goalLabel" :hint="locale.goalHint" :error="error" />
    </form>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
      <PhButton type="submit" form="goal-form" :loading="busy">{{ locale.save }}</PhButton>
    </template>
  </PhModal>
</template>
