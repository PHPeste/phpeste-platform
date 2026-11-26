<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import TemplateList from './TemplateList.vue'
import TemplateEditor from './TemplateEditor.vue'
import EmailPreview from './EmailPreview.vue'
import { useEmailTemplates } from '../composables/useEmailTemplates'
import locale from '../locales/pt-br'

const props = defineProps<{ editionId: string, readOnly: boolean }>()

const emails = useEmailTemplates(props.editionId)
const { templates, state, selectedId, selected, draft, dirty, errors, preview, previewState } = emails
const toast = useToast()

const busy = ref<'save' | 'test' | 'restore' | null>(null)
const pending = ref<string | null>(null)
const discarding = ref('')
const discardOpen = ref(false)
const restoreOpen = ref(false)

function choose(templateId: string) {
  if (templateId === selectedId.value) return
  if (dirty.value) {
    pending.value = templateId
    discarding.value = selected.value?.label ?? ''
    discardOpen.value = true
    return
  }
  emails.select(templateId)
}

function discard() {
  if (pending.value) emails.select(pending.value)
  pending.value = null
  discardOpen.value = false
}

async function save() {
  busy.value = 'save'
  const result = await emails.save()
  busy.value = null
  if (result.ok) toast.success(locale.saved)
  else toast.error(result.message)
}

async function sendTest() {
  busy.value = 'test'
  const result = await emails.sendTest()
  busy.value = null
  if (result.ok) toast.success(locale.testSent.replace('{email}', result.email))
  else toast.error(result.message)
}

async function restore() {
  busy.value = 'restore'
  const result = await emails.restore()
  busy.value = null
  restoreOpen.value = false
  if (result.ok) toast.success(locale.restored)
  else toast.error(result.message)
}

onMounted(() => emails.load())
onBeforeUnmount(() => emails.dispose())
</script>

<template>
  <section class="flex flex-col gap-6">
    <header class="flex flex-col gap-1.5">
      <h1 class="text-3xl leading-tight font-semibold">{{ locale.title }}</h1>
      <p class="text-(--ph-ink-2)">{{ locale.subtitle }}</p>
    </header>

    <p v-if="readOnly" role="status" class="alert alert-info alert-soft text-sm">{{ locale.readOnly }}</p>

    <PhPageSkeleton v-if="state === 'loading'" :cards="2" :lines="6" :columns="2" />
    <PhLoadError v-else-if="state === 'error'" :title="locale.loadError" @retry="emails.load" />

    <div v-else class="grid items-start gap-5 xl:grid-cols-2">
      <TemplateList :templates="templates" :selected-id="selectedId" @select="choose" />
      <section v-if="selected" class="card border border-base-300 bg-base-100 p-6" aria-labelledby="editor-title">
        <TemplateEditor
          :template="selected"
          :draft="draft"
          :errors="errors"
          :dirty="dirty"
          :read-only="readOnly"
          :busy="busy"
          @edit="emails.edit"
          @save="save"
          @test="sendTest"
          @restore="restoreOpen = true"
        >
          <EmailPreview :label="selected.label" :preview="preview" :state="previewState" @retry="emails.refreshPreview" />
        </TemplateEditor>
      </section>
    </div>

    <PhModal v-model="discardOpen" :title="locale.discardTitle.replace('{modelo}', discarding)">
      <p>{{ locale.discardText }}</p>
      <template #footer>
        <PhButton variant="ghost" @click="discardOpen = false">{{ locale.back }}</PhButton>
        <PhButton variant="danger" @click="discard">{{ locale.discard }}</PhButton>
      </template>
    </PhModal>
    <PhModal v-model="restoreOpen" :title="locale.restoreTitle.replace('{modelo}', selected?.label ?? '')">
      <p>{{ locale.restoreText }}</p>
      <template #footer>
        <PhButton variant="ghost" @click="restoreOpen = false">{{ locale.back }}</PhButton>
        <PhButton variant="danger" :loading="busy === 'restore'" @click="restore">{{ locale.restore }}</PhButton>
      </template>
    </PhModal>
  </section>
</template>
