<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import TicketCardPreview from './TicketCardPreview.vue'
import locale from '../locales/pt-br'
import type { MutationResult, Question, TicketType, TicketTypeInput } from '../types'

const props = defineProps<{
  type: TicketType | null
  feeBasisPoints: number
  save: (type: TicketType | null, input: TicketTypeInput) => Promise<MutationResult>
  remove: (type: TicketType) => Promise<MutationResult>
}>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [name: string], removed: [name: string] }>()

const form = reactive<TicketTypeInput>({ name: '', description: '', visibility: 'public', accessCode: '', minPerOrder: 1, maxPerOrder: 10, showRemaining: false, passFeeToBuyer: false, questions: [] })
const optionsText = ref<string[]>([])
const confirming = ref(false)
const busy = ref(false)
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)

watch(open, (value) => {
  if (!value) return
  const type = props.type
  Object.assign(form, {
    name: type?.name ?? '',
    description: type?.description ?? '',
    visibility: type?.visibility ?? 'public',
    accessCode: type?.accessCode ?? '',
    minPerOrder: type?.minPerOrder ?? 1,
    maxPerOrder: type?.maxPerOrder ?? 10,
    showRemaining: type?.showRemaining ?? false,
    passFeeToBuyer: type?.passFeeToBuyer ?? false,
    questions: (type?.questions ?? []).map((q) => ({ ...q, options: [...q.options] })),
  })
  optionsText.value = form.questions.map((q) => q.options.join('\n'))
  confirming.value = false
  errors.value = {}
  message.value = null
})

const visibilityOptions = Object.entries(locale.visibilityOptions).map(([value, label]) => ({ value, label }))
const questionTypeOptions = Object.entries(locale.questionTypes).map(([value, label]) => ({ value, label }))
const currentBatch = computed(() => props.type?.batches.find((b) => b.id === props.type?.currentBatchId) ?? props.type?.batches[0] ?? null)
const labels: Record<string, string> = { name: locale.typeName, accessCode: locale.accessCode, minPerOrder: locale.minPerOrder, maxPerOrder: locale.maxPerOrder, questions: locale.questionsTitle }
const fieldIds: Record<string, string> = { name: 'type-name', accessCode: 'type-code', minPerOrder: 'type-min', maxPerOrder: 'type-max' }

function addQuestion() {
  form.questions.push({ label: '', type: 'text', required: false, options: [] } satisfies Question)
  optionsText.value.push('')
}

function removeQuestion(index: number) {
  form.questions.splice(index, 1)
  optionsText.value.splice(index, 1)
}

function validate(): boolean {
  const found: Record<string, string> = {}
  if (!form.name.trim()) found.name = locale.required
  if (form.visibility === 'code' && !form.accessCode.trim()) found.accessCode = locale.required
  if (form.questions.some((q) => !q.label.trim())) found.questions = locale.required
  errors.value = found
  return Object.keys(found).length === 0
}

async function submit() {
  form.questions.forEach((q, i) => { q.options = (optionsText.value[i] ?? '').split('\n') })
  if (!validate()) return
  busy.value = true
  const result = await props.save(props.type, form)
  busy.value = false
  if (result.ok) {
    emit('saved', form.name.trim())
    open.value = false
    return
  }
  errors.value = result.fieldErrors
  message.value = Object.keys(result.fieldErrors).length ? null : result.message
}

async function confirmRemove() {
  if (!props.type) return
  busy.value = true
  const result = await props.remove(props.type)
  busy.value = false
  if (result.ok) {
    emit('removed', props.type.name)
    open.value = false
    return
  }
  message.value = result.message
}
</script>

<template>
  <PhDrawer v-model="open" :title="confirming && type ? locale.removeTypeTitle.replace('{nome}', type.name) : (type ? locale.editType : locale.newType)">
    <div v-if="!confirming" class="flex flex-col gap-6">
      <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
      <form id="ticket-type-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
        <PhInput id="type-name" v-model="form.name" :label="locale.typeName" required :error="errors.name" />
        <PhTextarea v-model="form.description" :label="locale.typeDescription" :error="errors.description" />
        <PhSelect v-model="form.visibility" :label="locale.visibility" :options="visibilityOptions" />
        <PhInput v-if="form.visibility === 'code'" id="type-code" v-model="form.accessCode" :label="locale.accessCode" :hint="locale.accessCodeHint" required :error="errors.accessCode" />
        <div class="grid grid-cols-2 gap-3">
          <PhInput id="type-min" v-model.number="form.minPerOrder" type="number" :label="locale.minPerOrder" :error="errors.minPerOrder" />
          <PhInput id="type-max" v-model.number="form.maxPerOrder" type="number" :label="locale.maxPerOrder" :error="errors.maxPerOrder" />
        </div>
        <PhCheckbox v-model="form.showRemaining" :label="locale.showRemaining" />
        <PhCheckbox v-model="form.passFeeToBuyer" :label="locale.passFee" :hint="locale.passFeeHint" />

        <fieldset class="flex flex-col gap-3 rounded-box border border-(--ph-line) p-4">
          <legend class="px-1 text-sm font-semibold">{{ locale.questionsTitle }}</legend>
          <p class="text-xs text-(--ph-ink-3)">{{ locale.questionsHint }}</p>
          <div v-for="(question, index) in form.questions" :key="index" class="flex flex-col gap-2 border-t border-(--ph-line) pt-3" :data-testid="`question-${index}`">
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium">{{ locale.question.replace('{n}', String(index + 1)) }}</span>
              <PhButton variant="ghost" :aria-label="locale.removeQuestion.replace('{n}', String(index + 1))" @click="removeQuestion(index)">×</PhButton>
            </div>
            <PhInput v-model="question.label" :label="locale.questionLabel" :error="!question.label.trim() ? errors.questions : null" />
            <PhSelect v-model="question.type" :label="locale.questionType" :options="questionTypeOptions" />
            <PhTextarea v-if="question.type === 'select'" v-model="optionsText[index]" :label="locale.questionOptions" />
            <PhCheckbox v-model="question.required" :label="locale.questionRequired" />
          </div>
          <PhButton variant="secondary" :disabled="form.questions.length >= 10" @click="addQuestion">{{ locale.addQuestion }}</PhButton>
        </fieldset>
      </form>

      <TicketCardPreview
        :name="form.name"
        :description="form.description"
        :visibility="form.visibility"
        :show-remaining="form.showRemaining"
        :pass-fee-to-buyer="form.passFeeToBuyer"
        :fee-basis-points="feeBasisPoints"
        :batch="currentBatch"
      />

      <div v-if="type" class="border-t border-(--ph-line) pt-4">
        <PhButton variant="ghost" class="text-error" @click="confirming = true">{{ locale.removeType }}</PhButton>
      </div>
    </div>
    <p v-else>{{ locale.removeTypeText }}</p>
    <p v-if="message" role="alert" class="alert alert-error alert-soft mt-4 text-sm">{{ message }}</p>
    <template #footer>
      <template v-if="!confirming">
        <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
        <PhButton type="submit" form="ticket-type-form" :loading="busy">{{ locale.saveType }}</PhButton>
      </template>
      <template v-else>
        <PhButton variant="ghost" @click="confirming = false">{{ locale.back }}</PhButton>
        <PhButton variant="danger" :loading="busy" @click="confirmRemove">{{ locale.removeType }}</PhButton>
      </template>
    </template>
  </PhDrawer>
</template>
