<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { onBeforeRouteLeave, useRouter } from 'vue-router'
import { useToast } from '@/application/shared/composables/useToast'
import ColorTokens from './ColorTokens.vue'
import ContrastReport from './ContrastReport.vue'
import LogoUploader from './LogoUploader.vue'
import PresetPicker from './PresetPicker.vue'
import ThemePreview from './ThemePreview.vue'
import { useThemeEditor } from '../composables/useThemeEditor'
import locale from '../locales/pt-br'
import type { LogoSlot } from '../types'

const props = defineProps<{ editionId: string, editionName: string, dates: string, readOnly: boolean }>()

const toast = useToast()
const editor = useThemeEditor(props.editionId)
const { state, presets, meta, loadState, busy, errorMessage, uploadingSlot, form, contrast, failing, invalidTokens, isDirty, canPublish } = editor

const leaveOpen = ref(false)
const hasLeft = ref(false)
let pendingPath: string | null = null
const router = useRouter()

const fontOptions = computed(() => meta.value.fonts.map((font) => ({ value: font, label: font })))
const publishedLabel = computed(() => {
  if (!state.value?.publishedAt) return locale.neverPublished
  return locale.publishedAt.replace('{data}', new Date(state.value.publishedAt).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }))
})
const blockedReason = computed(() => {
  if (invalidTokens.value.length) return locale.blocked.replace('{pares}', locale.hexInvalid)
  if (!failing.value.length) return null
  return locale.blocked.replace('{pares}', failing.value.map((pair) => `${pair.label} (${pair.ratio.toFixed(2).replace('.', ',')}:1)`).join('; '))
})
const hasPendingWork = computed(() => isDirty.value || !!state.value?.hasUnpublishedChanges)

async function act(action: () => Promise<boolean>, message: string) {
  if (await action()) toast.success(message)
}

const onUpload = (slot: LogoSlot, file: File) => editor.uploadLogo(slot, file)
const onRemove = (slot: LogoSlot) => editor.removeLogo(slot)

onBeforeRouteLeave((to) => {
  if (hasLeft.value || !hasPendingWork.value || props.readOnly) return true
  pendingPath = to.fullPath
  leaveOpen.value = true
  return false
})

async function leaveAnyway() {
  leaveOpen.value = false
  if (!pendingPath) return
  hasLeft.value = true
  await router.push(pendingPath)
}

function onBeforeUnload(event: BeforeUnloadEvent) {
  if (isDirty.value && !hasLeft.value) event.preventDefault()
}

onMounted(() => {
  editor.load()
  window.addEventListener('beforeunload', onBeforeUnload)
})
onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload))
</script>

<template>
  <section class="flex flex-col gap-7">
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl leading-tight font-semibold">{{ locale.title }}</h1>
        <p class="text-(--ph-ink-2)">{{ locale.subtitle }}</p>
        <p v-if="state" class="text-xs text-(--ph-ink-3)">
          {{ publishedLabel }}<template v-if="hasPendingWork"> · <b class="font-medium text-(--ph-warn)">{{ locale.unpublished }}</b></template>
        </p>
      </div>
      <div v-if="state && !readOnly" class="flex flex-wrap gap-3">
        <PhButton variant="ghost" :loading="busy === 'discard'" :disabled="!hasPendingWork || !!busy" @click="act(editor.discard, locale.discarded)">{{ locale.discard }}</PhButton>
        <PhButton variant="ghost" :loading="busy === 'save'" :disabled="!isDirty || !!busy || failing.length > 0" @click="act(editor.saveDraft, locale.saved)">{{ locale.saveDraft }}</PhButton>
        <PhButton :loading="busy === 'publish'" :disabled="!canPublish || !!busy" aria-describedby="publish-blocked" @click="act(editor.publish, locale.published)">{{ locale.publish }}</PhButton>
      </div>
    </header>

    <p v-if="readOnly" role="status" class="alert alert-info alert-soft text-sm">{{ locale.readOnly }}</p>
    <p v-if="blockedReason && !readOnly" id="publish-blocked" role="alert" class="alert alert-error alert-soft text-sm">{{ blockedReason }}</p>
    <p v-if="errorMessage" role="alert" class="alert alert-error alert-soft text-sm">{{ errorMessage }}</p>

    <p v-if="loadState === 'loading'" role="status" aria-live="polite" class="flex items-center gap-2 text-(--ph-ink-2)">
      <span class="loading loading-spinner loading-sm" aria-hidden="true" />{{ locale.loading }}
    </p>
    <div v-else-if="loadState === 'error'" role="alert" class="alert alert-error alert-soft">
      <span>{{ locale.loadError }}</span>
      <PhButton variant="ghost" @click="editor.load">{{ locale.retry }}</PhButton>
    </div>

    <div v-else-if="state" class="grid items-start gap-6 lg:grid-cols-2">
      <div class="flex flex-col gap-5">
        <PresetPicker :presets="presets" :current="form.colors" :disabled="readOnly" :busy="!!busy" @apply="editor.applyPreset" />

        <PhCard>
          <div class="flex flex-col gap-4">
            <h2 class="text-lg font-semibold">{{ locale.brandTitle }}</h2>
            <LogoUploader :logos="state.draft.logos" :uploading="uploadingSlot" :disabled="readOnly" @upload="onUpload" @remove="onRemove" />
            <div class="grid gap-4 sm:grid-cols-2">
              <PhSelect id="theme-font" v-model="form.heading" :label="locale.font" :options="fontOptions" :disabled="readOnly" @update:model-value="form.body = $event" />
              <div class="flex flex-col gap-1.5">
                <label for="theme-radius" class="text-sm font-medium">{{ locale.radius }}</label>
                <div class="flex items-center gap-3">
                  <input id="theme-radius" v-model.number="form.radius" type="range" class="range range-primary range-sm" :min="meta.radius.min" :max="meta.radius.max" :disabled="readOnly">
                  <span class="w-12 text-right text-sm tabular-nums">{{ locale.radiusValue.replace('{n}', String(form.radius)) }}</span>
                </div>
              </div>
            </div>
          </div>
        </PhCard>

        <ColorTokens v-model="form.colors" :tokens="meta.tokens" :disabled="readOnly" />
        <ContrastReport :results="contrast" />

        <PhButton v-if="state.canRevert && !readOnly" variant="ghost" class="self-start" :loading="busy === 'revert'" :disabled="!!busy" @click="act(editor.revert, locale.reverted)">
          {{ locale.revert }}
        </PhButton>
      </div>

      <div class="lg:sticky lg:top-4">
        <ThemePreview :colors="form.colors" :font="form.heading" :radius="form.radius" :logo="state.draft.logos.primary" :edition-name="editionName" :dates="dates" />
      </div>
    </div>

    <PhModal v-model="leaveOpen" :title="locale.leaveTitle">
      <p>{{ isDirty ? locale.leaveUnsaved : locale.leaveUnpublished }}</p>
      <template #footer>
        <PhButton variant="ghost" @click="leaveAnyway">{{ locale.leave }}</PhButton>
        <PhButton @click="leaveOpen = false">{{ locale.stay }}</PhButton>
      </template>
    </PhModal>
  </section>
</template>
