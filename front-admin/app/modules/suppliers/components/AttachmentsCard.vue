<script setup lang="ts">
import { ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import { formatSize } from '../composables/format'
import locale from '../locales/pt-br'
import type { Attachment, AttachmentKind, MutationResult } from '../types'

const props = defineProps<{
  attachments: Attachment[]
  disabled: boolean
  upload: (kind: AttachmentKind, file: File) => Promise<MutationResult>
  remove: (attachmentId: string) => Promise<MutationResult>
  url: (attachmentId: string) => Promise<string | null>
}>()

const toast = useToast()
const kind = ref<string>('quote')
const uploading = ref<string | null>(null)
const over = ref(false)
const input = ref<HTMLInputElement | null>(null)
const kinds = Object.entries(locale.kinds).map(([value, label]) => ({ value, label }))

async function send(files: FileList | null | undefined) {
  for (const file of Array.from(files ?? [])) {
    uploading.value = file.name
    const result = await props.upload(kind.value as AttachmentKind, file)
    if (result.ok) toast.success(locale.uploaded.replace('{arquivo}', file.name))
    else toast.error(result.message)
  }
  uploading.value = null
  if (input.value) input.value.value = ''
}

function drop(event: DragEvent) {
  over.value = false
  if (!props.disabled) send(event.dataTransfer?.files)
}

async function download(attachment: Attachment) {
  const link = await props.url(attachment.id)
  if (!link) {
    toast.error(locale.linkError)
    return
  }
  window.location.assign(link)
}

async function removeOne(attachment: Attachment) {
  const result = await props.remove(attachment.id)
  if (result.ok) toast.success(locale.attachmentRemoved)
  else toast.error(result.message)
}
</script>

<template>
  <section class="card border border-base-300 bg-base-100 p-5" aria-labelledby="attachments-title">
    <h2 id="attachments-title" class="text-lg font-semibold">{{ locale.attachments }}</h2>
    <p class="mb-4 text-xs text-(--ph-ink-3)">{{ locale.attachmentsHint }}</p>
    <ul v-if="attachments.length" class="mb-4 flex flex-col divide-y divide-(--ph-line) rounded-(--radius-box) border border-(--ph-line)" data-testid="supplier-attachments">
      <li v-for="attachment in attachments" :key="attachment.id" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
        <div class="min-w-0">
          <p class="truncate font-medium">{{ attachment.name }}</p>
          <p class="text-xs text-(--ph-ink-3)">{{ attachment.kindLabel }} · {{ formatSize(attachment.sizeBytes) }}<template v-if="attachment.uploadedBy"> · {{ locale.by.replace('{nome}', attachment.uploadedBy) }}</template></p>
        </div>
        <div class="flex gap-1">
          <button type="button" class="btn btn-ghost btn-sm" :aria-label="locale.downloadLabel.replace('{arquivo}', attachment.name)" @click="download(attachment)">{{ locale.download }}</button>
          <button v-if="!disabled" type="button" class="btn btn-ghost btn-sm text-error" :aria-label="locale.removeAttachment.replace('{arquivo}', attachment.name)" @click="removeOne(attachment)">{{ locale.remove }}</button>
        </div>
      </li>
    </ul>
    <p v-else class="mb-4 text-sm text-(--ph-ink-3)">{{ locale.noAttachments }}</p>

    <div v-if="!disabled" class="flex flex-col gap-3">
      <PhSelect id="attachment-kind" v-model="kind" :label="locale.attachmentKind" :options="kinds" />
      <label
        class="flex cursor-pointer flex-col items-center gap-1 rounded-(--radius-box) border-2 border-dashed border-(--ph-line) px-4 py-6 text-center text-sm transition-colors"
        :class="{ 'border-(--ph-brand) bg-(--ph-surface-2)': over }"
        @dragover.prevent="over = true"
        @dragleave="over = false"
        @drop.prevent="drop"
      >
        <span class="font-medium">{{ uploading ? locale.uploading.replace('{arquivo}', uploading) : locale.dropzone }}</span>
        <span class="text-xs text-(--ph-ink-3)">{{ locale.dropzoneHint }}</span>
        <input ref="input" type="file" class="sr-only" multiple :aria-label="locale.dropzone" :disabled="uploading !== null" data-testid="attachment-input" @change="send(($event.target as HTMLInputElement).files)">
      </label>
    </div>
  </section>
</template>
