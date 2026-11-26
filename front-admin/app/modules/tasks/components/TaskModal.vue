<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import locale from '../locales/pt-br'
import type { Column, LinkGroup, Member, MutationResult, Task, TaskInput } from '../types'

const props = defineProps<{
  task: Task | null
  columnId: string
  columns: Column[]
  members: Member[]
  linkGroups: LinkGroup[]
  save: (taskId: string | null, input: TaskInput) => Promise<MutationResult>
  remove: (taskId: string) => Promise<MutationResult>
}>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [message: string] }>()

const form = reactive<TaskInput>({ title: '', description: '', assigneeId: '', dueOn: '', link: '', columnId: '' })
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)
const busy = ref<'save' | 'remove' | null>(null)
const confirmRemove = ref(false)
const members = computed(() => [{ value: '', label: locale.noAssignee }, ...props.members.map((member) => ({ value: member.id, label: member.name }))])
const links = computed(() => [{ value: '', label: locale.linkNone }, ...props.linkGroups.flatMap((group) => group.options.map((option) => ({ value: `${group.type}:${option.id}`, label: `${group.label} · ${option.name}` })))])
const columnOptions = computed(() => props.columns.map((column) => ({ value: column.id, label: column.name })))
const labels: Record<string, string> = { title: locale.fieldTitle, description: locale.fieldDescription, assigneeId: locale.fieldAssignee, dueOn: locale.fieldDue, link: locale.fieldLink, columnId: locale.fieldColumn }
const fieldIds: Record<string, string> = Object.fromEntries(Object.keys(labels).map((key) => [key, `task-${key}`]))

watch(open, (value) => {
  if (!value) return
  const task = props.task
  Object.assign(form, {
    title: task?.title ?? '',
    description: task?.description ?? '',
    assigneeId: task?.assigneeId ?? '',
    dueOn: task?.dueOn ?? '',
    link: task?.link ? `${task.link.type}:${task.link.id}` : '',
    columnId: task?.columnId ?? props.columnId,
  })
  errors.value = {}
  message.value = null
  confirmRemove.value = false
})

async function submit() {
  if (form.title.trim() === '') {
    errors.value = { title: locale.required }
    return
  }
  busy.value = 'save'
  const result = await props.save(props.task?.id ?? null, { ...form })
  busy.value = null
  if (result.ok) {
    emit('saved', locale.saved)
    open.value = false
    return
  }
  errors.value = result.fieldErrors
  message.value = Object.keys(result.fieldErrors).length ? null : result.message
}

async function removeIt() {
  if (!props.task) return
  busy.value = 'remove'
  const result = await props.remove(props.task.id)
  busy.value = null
  if (result.ok) {
    emit('saved', locale.removed)
    open.value = false
  } else {
    message.value = result.message
  }
}
</script>

<template>
  <PhModal v-model="open" :title="task ? locale.modalEdit : locale.modalNew">
    <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
    <form id="task-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <PhInput id="task-title" v-model="form.title" :label="locale.fieldTitle" required :error="errors.title" />
      <PhTextarea id="task-description" v-model="form.description" :label="locale.fieldDescription" :rows="3" :error="errors.description" />
      <div class="grid gap-3 sm:grid-cols-2">
        <PhSelect id="task-assigneeId" v-model="form.assigneeId" :label="locale.fieldAssignee" :options="members" :error="errors.assigneeId" />
        <PhInput id="task-dueOn" v-model="form.dueOn" type="date" :label="locale.fieldDue" :error="errors.dueOn" />
      </div>
      <div class="grid gap-3 sm:grid-cols-2">
        <PhSelect id="task-link" v-model="form.link" :label="locale.fieldLink" :options="links" :error="errors.link" />
        <PhSelect v-if="!task" id="task-columnId" v-model="form.columnId" :label="locale.fieldColumn" :options="columnOptions" :error="errors.columnId" />
      </div>
      <p v-if="confirmRemove && task" role="alert" class="alert alert-warning alert-soft text-sm">{{ locale.removeTitle.replace('{titulo}', task.title) }} {{ locale.removeText }}</p>
    </form>
    <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
    <template #footer>
      <template v-if="task">
        <PhButton v-if="!confirmRemove" variant="ghost" class="mr-auto text-error" @click="confirmRemove = true">{{ locale.remove }}</PhButton>
        <PhButton v-else variant="danger" class="mr-auto" :loading="busy === 'remove'" @click="removeIt">{{ locale.remove }}</PhButton>
      </template>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
      <PhButton type="submit" form="task-form" :loading="busy === 'save'">{{ locale.save }}</PhButton>
    </template>
  </PhModal>
</template>
