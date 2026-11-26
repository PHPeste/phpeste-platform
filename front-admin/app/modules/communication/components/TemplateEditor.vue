<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { wrap } from '../composables/format'
import locale from '../locales/pt-br'
import type { Content, ContentField, EmailTemplate } from '../types'

const props = defineProps<{
  template: EmailTemplate
  draft: Content
  errors: Record<ContentField, string | null>
  dirty: boolean
  readOnly: boolean
  busy: 'save' | 'test' | 'restore' | null
}>()
const emit = defineEmits<{ edit: [field: ContentField, value: string], save: [], test: [], restore: [] }>()

const FIELD_IDS: Record<ContentField, string> = { subject: 'template-subject', body: 'template-body' }
const labels = { subject: locale.fieldSubject, body: locale.fieldBody }
const attempted = ref(false)
const lastField = ref<ContentField>('body')

watch(() => props.template.id, () => {
  attempted.value = false
  lastField.value = 'body'
})

const shownErrors = computed(() => (attempted.value ? props.errors : { subject: null, body: null }))
const fieldError = (field: ContentField) => (attempted.value || props.draft[field].trim() !== '' ? props.errors[field] : null)

const subject = computed({ get: () => props.draft.subject, set: (value: string) => emit('edit', 'subject', value) })
const body = computed({ get: () => props.draft.body, set: (value: string) => emit('edit', 'body', value) })

async function insert(name: string) {
  const field = lastField.value
  const element = document.getElementById(FIELD_IDS[field]) as HTMLInputElement | HTMLTextAreaElement | null
  const text = props.draft[field]
  const start = element?.selectionStart ?? text.length
  const end = element?.selectionEnd ?? text.length
  const token = wrap(name)
  emit('edit', field, `${text.slice(0, start)}${token}${text.slice(end)}`)
  await nextTick()
  element?.focus()
  element?.setSelectionRange(start + token.length, start + token.length)
}

function submit() {
  attempted.value = true
  if (props.errors.subject || props.errors.body) return
  emit('save')
}

function test() {
  attempted.value = true
  if (props.errors.subject || props.errors.body) return
  emit('test')
}
</script>

<template>
  <form id="template-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h2 id="editor-title" class="text-lg font-semibold">{{ template.label }}</h2>
      <div class="flex flex-wrap gap-2">
        <PhBadge v-if="dirty" status="warn">{{ locale.unsaved }}</PhBadge>
        <PhBadge v-if="template.customized" status="info">{{ locale.customized }}</PhBadge>
        <PhBadge status="neutral" :title="template.transactional ? locale.transactionalHint : locale.marketingHint">{{ template.transactional ? locale.transactional : locale.marketing }}</PhBadge>
      </div>
    </div>
    <p class="text-sm text-(--ph-ink-2)">{{ template.trigger }}. {{ template.transactional ? locale.transactionalHint : locale.marketingHint }}</p>

    <PhFormErrors :errors="shownErrors" :labels="labels" :field-ids="FIELD_IDS" />

    <div @focusin="lastField = 'subject'">
      <PhInput :id="FIELD_IDS.subject" v-model="subject" :label="locale.fieldSubject" :error="fieldError('subject')" :disabled="readOnly" required />
    </div>
    <div @focusin="lastField = 'body'">
      <PhTextarea :id="FIELD_IDS.body" v-model="body" :label="locale.fieldBody" :rows="8" :hint="locale.bodyHint" :error="fieldError('body')" :disabled="readOnly" required />
    </div>

    <div class="flex flex-col gap-1.5" role="group" :aria-label="locale.variablesLabel">
      <span class="text-sm font-medium">{{ locale.variablesLabel }}</span>
      <div class="flex flex-wrap gap-1.5">
        <button
          v-for="name in template.variables"
          :key="name"
          type="button"
          class="btn btn-ghost btn-sm font-mono"
          :aria-label="locale.insertVariable.replace('{variavel}', wrap(name))"
          :disabled="readOnly"
          @mousedown.prevent
          @click="insert(name)"
        >
          {{ wrap(name) }}
        </button>
      </div>
      <p class="text-xs text-(--ph-ink-3)">{{ locale.actionButton.replace('{rotulo}', template.actionLabel) }}</p>
    </div>

    <slot />

    <div v-if="!readOnly" class="flex flex-wrap gap-2">
      <PhButton type="submit" :loading="busy === 'save'" :disabled="!dirty || busy !== null">{{ locale.save }}</PhButton>
      <PhButton variant="ghost" :loading="busy === 'test'" :disabled="busy !== null" @click="test">{{ locale.sendTest }}</PhButton>
      <PhButton v-if="template.customized" variant="ghost" :disabled="busy !== null" @click="emit('restore')">{{ locale.restore }}</PhButton>
    </div>
  </form>
</template>
