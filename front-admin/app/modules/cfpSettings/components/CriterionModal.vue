<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import locale from '../locales/pt-br'
import type { Criterion, CriterionInput, MutationResult } from '../types'

const props = defineProps<{
  criterion: Criterion | null
  submittedReviews: number
  save: (criterion: Criterion | null, input: CriterionInput, confirmed?: boolean) => Promise<MutationResult>
  remove: (criterion: Criterion, confirmed?: boolean) => Promise<MutationResult>
}>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [name: string], removed: [name: string] }>()

const WEIGHTS = [1, 2, 3, 4, 5].map((n) => ({ value: String(n), label: String(n) }))

const name = ref('')
const description = ref('')
const weight = ref('1')
const step = ref<'form' | 'impact' | 'remove'>('form')
const pending = ref<'save' | 'remove'>('save')
const busy = ref(false)
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)

watch(open, (value) => {
  if (!value) return
  name.value = props.criterion?.name ?? ''
  description.value = props.criterion?.description ?? ''
  weight.value = String(props.criterion?.weight ?? 1)
  step.value = 'form'
  errors.value = {}
  message.value = null
})

const reviewsText = computed(() => (props.submittedReviews === 1 ? locale.reviewsExistOne : locale.reviewsExist.replace('{n}', String(props.submittedReviews))))
const title = computed(() => {
  if (step.value === 'impact') return locale.impactTitle
  if (step.value === 'remove' && props.criterion) return locale.removeCriterionTitle.replace('{nome}', props.criterion.name)
  return props.criterion ? locale.editCriterion : locale.newCriterion
})

const input = (): CriterionInput => ({ name: name.value.trim(), description: description.value.trim(), weight: Number(weight.value) })

function handle(result: MutationResult, action: 'save' | 'remove', done: () => void) {
  if (result.ok) return done()
  if ('needsConfirmation' in result) {
    pending.value = action
    step.value = 'impact'
    return
  }
  errors.value = result.fieldErrors
  message.value = Object.keys(result.fieldErrors).some((key) => ['name', 'description', 'weight'].includes(key)) ? null : result.message
  if (action === 'save') step.value = 'form'
}

async function submit(confirmed = false) {
  errors.value = name.value.trim() ? {} : { name: locale.required }
  if (errors.value.name) return
  busy.value = true
  const result = await props.save(props.criterion, input(), confirmed)
  busy.value = false
  handle(result, 'save', () => {
    emit('saved', input().name)
    open.value = false
  })
}

async function confirmRemove(confirmed = false) {
  if (!props.criterion) return
  busy.value = true
  const result = await props.remove(props.criterion, confirmed)
  busy.value = false
  handle(result, 'remove', () => {
    emit('removed', props.criterion!.name)
    open.value = false
  })
}

const confirmImpact = () => (pending.value === 'save' ? submit(true) : confirmRemove(true))
</script>

<template>
  <PhModal v-model="open" :title="title">
    <form v-if="step === 'form'" id="criterion-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit()">
      <PhInput v-model="name" :label="locale.criterionName" required :error="errors.name" />
      <PhTextarea v-model="description" :label="locale.criterionDescription" :rows="2" :error="errors.description" />
      <PhSelect v-model="weight" :label="locale.weight" :options="WEIGHTS" :error="errors.weight" />
      <div v-if="criterion" class="border-t border-(--ph-line) pt-4">
        <PhButton variant="ghost" class="text-error" @click="step = 'remove'">{{ locale.removeCriterion }}</PhButton>
      </div>
    </form>
    <div v-else-if="step === 'impact'" class="flex flex-col gap-3">
      <p class="font-medium">{{ reviewsText }}</p>
      <p>{{ locale.impactText }}</p>
    </div>
    <p v-else>{{ locale.removeCriterionText }}</p>
    <p v-if="message" role="alert" class="alert alert-error alert-soft mt-4 text-sm">{{ message }}</p>
    <template #footer>
      <template v-if="step === 'form'">
        <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
        <PhButton type="submit" form="criterion-form" :loading="busy">{{ locale.saveCriterion }}</PhButton>
      </template>
      <template v-else-if="step === 'impact'">
        <PhButton variant="ghost" @click="step = pending === 'save' ? 'form' : 'remove'">{{ locale.back }}</PhButton>
        <PhButton :loading="busy" @click="confirmImpact">{{ locale.confirmImpact }}</PhButton>
      </template>
      <template v-else>
        <PhButton variant="ghost" @click="step = 'form'">{{ locale.back }}</PhButton>
        <PhButton variant="danger" :loading="busy" @click="confirmRemove()">{{ locale.removeCriterion }}</PhButton>
      </template>
    </template>
  </PhModal>
</template>
