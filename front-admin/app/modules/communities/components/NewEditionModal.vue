<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useNewEdition } from '../composables/useNewEdition'
import { BRAZILIAN_STATES, slugify } from '../composables/format'
import locale from '../locales/pt-br'
import type { CommunitySummary } from '../types'

const props = defineProps<{ community: CommunitySummary | null }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ created: [editionId: string] }>()

type Step = 'start' | 'details' | 'copy'

const { isSubmitting, isCloning, errorMessage, fieldErrors, cloneParts, neverCopied, loadCloneOptions, createBlank, createFromPrevious } = useNewEdition()

const sources = computed(() => props.community?.editions.filter((edition) => edition.role === 'organizador') ?? [])
const step = ref<Step>('details')
const mode = ref<'blank' | 'clone'>('blank')
const sourceId = ref('')
const selectedParts = ref<string[]>([])
const slugEdited = ref(false)
const submitted = ref(false)
const form = reactive({ name: '', slug: '', startsOn: '', endsOn: '', city: '', state: '' })

const stateOptions = BRAZILIAN_STATES.map((uf) => ({ value: uf, label: uf }))
const sourceOptions = computed(() => sources.value.map((edition) => ({ value: edition.id, label: edition.name })))
const title = computed(() => locale.wizardTitle.replace('{comunidade}', props.community?.name ?? ''))

watch(open, (isOpen) => {
  if (!isOpen) return
  Object.assign(form, { name: '', slug: '', startsOn: '', endsOn: '', city: '', state: '' })
  slugEdited.value = false
  submitted.value = false
  mode.value = sources.value.length > 0 ? 'clone' : 'blank'
  sourceId.value = sources.value[0]?.id ?? ''
  step.value = sources.value.length > 0 ? 'start' : 'details'
})

watch(() => form.name, (name) => {
  if (!slugEdited.value) form.slug = slugify(name)
})

watch([mode, sourceId], async () => {
  if (mode.value === 'clone' && sourceId.value) {
    await loadCloneOptions(sourceId.value)
    selectedParts.value = cloneParts.value.map((part) => part.id)
  }
})

const nameError = computed(() => fieldErrors.value?.name?.[0] ?? (submitted.value && !form.name.trim() ? locale.nameRequired : null))
const slugError = computed(() => fieldErrors.value?.slug?.[0] ?? (submitted.value && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(form.slug) ? locale.slugInvalid : null))
const endsError = computed(() => fieldErrors.value?.ends_on?.[0] ?? (submitted.value && form.startsOn && form.endsOn && form.endsOn < form.startsOn ? locale.endsBeforeStart : null))
const detailsValid = computed(() => !nameError.value && !slugError.value && !endsError.value)

const willCopy = computed(() => cloneParts.value.filter((part) => selectedParts.value.includes(part.id)))

function next() {
  if (step.value === 'start') {
    step.value = 'details'
    return
  }

  submitted.value = true
  if (detailsValid.value && mode.value === 'clone') step.value = 'copy'
}

function back() {
  step.value = step.value === 'copy' ? 'details' : 'start'
}

function togglePart(id: string, checked: boolean) {
  selectedParts.value = checked ? [...selectedParts.value, id] : selectedParts.value.filter((part) => part !== id)
}

async function onSubmit() {
  submitted.value = true
  if (!detailsValid.value || !props.community) return

  const editionId = mode.value === 'clone'
    ? await createFromPrevious({ sourceEditionId: sourceId.value, name: form.name.trim(), slug: form.slug, parts: selectedParts.value })
    : await createBlank(props.community.id, { ...form, name: form.name.trim() })

  if (editionId) {
    open.value = false
    emit('created', editionId)
  } else if (fieldErrors.value) {
    step.value = 'details'
  }
}
</script>

<template>
  <PhModal v-model="open" :title="title">
    <form id="new-edition-form" class="flex flex-col gap-4" novalidate @submit.prevent="step === 'details' && mode === 'clone' ? next() : onSubmit()">
      <fieldset v-if="step === 'start'" class="flex flex-col gap-3">
        <legend class="mb-2 text-sm font-semibold">{{ locale.stepStart }}</legend>
        <label class="flex cursor-pointer items-start gap-3 rounded-(--ph-radius) border border-(--ph-line) p-3">
          <input v-model="mode" type="radio" value="clone" class="radio radio-primary mt-0.5" name="edition-start">
          <span class="flex flex-col"><b class="font-medium">{{ locale.fromPrevious }}</b><span class="text-xs text-(--ph-ink-3)">{{ locale.fromPreviousHint }}</span></span>
        </label>
        <label class="flex cursor-pointer items-start gap-3 rounded-(--ph-radius) border border-(--ph-line) p-3">
          <input v-model="mode" type="radio" value="blank" class="radio radio-primary mt-0.5" name="edition-start">
          <span class="flex flex-col"><b class="font-medium">{{ locale.fromScratch }}</b><span class="text-xs text-(--ph-ink-3)">{{ locale.fromScratchHint }}</span></span>
        </label>
        <PhSelect v-if="mode === 'clone'" id="edition-source" v-model="sourceId" :label="locale.sourceEdition" :options="sourceOptions" />
      </fieldset>

      <fieldset v-else-if="step === 'details'" class="flex flex-col gap-4">
        <legend class="mb-2 text-sm font-semibold">{{ locale.stepDetails }}</legend>
        <PhInput id="edition-name" v-model="form.name" :label="locale.editionName" :placeholder="locale.editionNamePlaceholder" :error="nameError" />
        <PhInput id="edition-slug" v-model="form.slug" :label="locale.slug" :hint="locale.slugHint" :error="slugError" @update:model-value="slugEdited = true" />
        <template v-if="mode === 'blank'">
          <div class="grid gap-4 sm:grid-cols-2">
            <PhInput id="edition-starts" v-model="form.startsOn" type="date" :label="locale.startsOn" />
            <PhInput id="edition-ends" v-model="form.endsOn" type="date" :label="locale.endsOn" :error="endsError" />
          </div>
          <div class="grid gap-4 sm:grid-cols-[1fr_8rem]">
            <PhInput id="edition-city" v-model="form.city" :label="locale.city" />
            <PhSelect id="edition-state" v-model="form.state" :label="locale.state" :options="stateOptions" :placeholder="locale.statePlaceholder" />
          </div>
        </template>
        <p v-else class="text-xs text-(--ph-ink-3)">{{ locale.datesAfterClone }}</p>
      </fieldset>

      <fieldset v-else class="flex flex-col gap-3">
        <legend class="mb-2 text-sm font-semibold">{{ locale.stepCopy }}</legend>
        <PhCheckbox
          v-for="part in cloneParts"
          :id="`part-${part.id}`"
          :key="part.id"
          :model-value="selectedParts.includes(part.id)"
          :label="part.label"
          @update:model-value="togglePart(part.id, $event)"
        />
        <div class="grid gap-3 rounded-(--ph-radius) bg-(--ph-surface-2) p-4 sm:grid-cols-2" aria-live="polite">
          <div>
            <h3 class="mb-1 text-xs font-semibold tracking-wide text-(--ph-ink-3) uppercase">{{ locale.willCopy }}</h3>
            <ul v-if="willCopy.length" class="list-disc pl-4 text-sm"><li v-for="part in willCopy" :key="part.id">{{ part.label }}</li></ul>
            <p v-else class="text-sm text-(--ph-ink-2)">{{ locale.nothingSelected }}</p>
          </div>
          <div>
            <h3 class="mb-1 text-xs font-semibold tracking-wide text-(--ph-ink-3) uppercase">{{ locale.neverCopied }}</h3>
            <ul class="list-disc pl-4 text-sm text-(--ph-ink-2)"><li v-for="item in neverCopied" :key="item">{{ item }}</li></ul>
          </div>
        </div>
        <div v-if="isCloning" class="flex flex-col gap-2">
          <p role="status" class="text-sm text-(--ph-ink-2)">{{ locale.cloning }}</p>
          <progress class="progress progress-primary w-full" :aria-label="locale.cloning" />
        </div>
      </fieldset>

      <div v-if="errorMessage" role="alert" class="alert alert-error alert-soft text-sm">{{ errorMessage }}</div>
    </form>

    <template #footer>
      <PhButton v-if="step !== 'start' && (step === 'copy' || sources.length > 0)" variant="ghost" @click="back">{{ locale.back }}</PhButton>
      <PhButton v-else variant="ghost" @click="open = false">{{ locale.cancel }}</PhButton>
      <PhButton v-if="step === 'start'" @click="next">{{ locale.next }}</PhButton>
      <PhButton v-else-if="step === 'details' && mode === 'clone'" type="submit" form="new-edition-form">{{ locale.next }}</PhButton>
      <PhButton v-else type="submit" form="new-edition-form" :loading="isSubmitting">{{ locale.createEdition }}</PhButton>
    </template>
  </PhModal>
</template>
