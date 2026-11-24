<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import locale from '../locales/pt-br'
import type { Deal, MoveResult, Stage } from '../types'

const props = defineProps<{ deal: Deal, company: string, move: (stage: Stage, lostReason: string, confirm?: boolean) => Promise<MoveResult> }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [] }>()

const stage = ref<Stage>('prospecting')
const reason = ref('')
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)
const confirmText = ref<string | null>(null)
const busy = ref(false)
const options = Object.entries(locale.stages).map(([value, label]) => ({ value, label }))
const labels: Record<string, string> = { stage: locale.stage, lostReason: locale.lostReason }
const fieldIds: Record<string, string> = { stage: 'stage-select', lostReason: 'stage-lost-reason' }
const model = computed({ get: () => stage.value, set: (value: string) => { stage.value = value as Stage } })

watch(open, (value) => {
  if (!value) return
  stage.value = props.deal.stage
  reason.value = ''
  errors.value = {}
  message.value = null
  confirmText.value = null
})

async function submit() {
  if (stage.value === 'lost' && reason.value.trim() === '') {
    errors.value = { lostReason: locale.required }
    return
  }
  busy.value = true
  const result = await props.move(stage.value, reason.value, confirmText.value !== null)
  busy.value = false
  if (result.ok) {
    emit('saved')
    open.value = false
    return
  }
  if (result.needsConfirm) {
    confirmText.value = result.message
    errors.value = {}
    return
  }
  errors.value = result.fieldErrors
  message.value = Object.keys(result.fieldErrors).length ? null : result.message
}
</script>

<template>
  <PhModal v-model="open" :title="locale.stageModalTitle.replace('{empresa}', company)">
    <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
    <form id="stage-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <PhSelect id="stage-select" v-model="model" :label="locale.stage" :options="options" :error="errors.stage" />
      <PhTextarea v-if="stage === 'lost'" id="stage-lost-reason" v-model="reason" :label="locale.lostReason" :hint="locale.lostReasonHint" required :rows="3" :error="errors.lostReason" />
      <p v-if="confirmText" role="alert" class="alert alert-warning alert-soft text-sm" data-testid="stage-confirm">{{ confirmText }}</p>
    </form>
    <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
      <PhButton type="submit" form="stage-form" :loading="busy">{{ confirmText ? locale.confirmText : locale.save }}</PhButton>
    </template>
  </PhModal>
</template>
