<script setup lang="ts">
import { ref, watch } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import locale from '../locales/pt-br'
import type { MutationResult, ReminderPreview } from '../types'

const props = defineProps<{ preview: () => Promise<ReminderPreview | null>, remind: (recipients: number) => Promise<MutationResult> }>()
const open = defineModel<boolean>({ default: false })

const toast = useToast()
const state = ref<'loading' | 'ready' | 'error'>('loading')
const data = ref<ReminderPreview | null>(null)
const message = ref<string | null>(null)
const busy = ref(false)

async function load() {
  state.value = 'loading'
  message.value = null
  data.value = await props.preview()
  state.value = data.value ? 'ready' : 'error'
}

watch(open, (value) => {
  if (value) load()
})

const plural = (one: string, many: string, n: number) => (n === 1 ? one : many.replace('{n}', String(n)))

async function confirm() {
  if (!data.value) return
  busy.value = true
  const result = await props.remind(data.value.recipients)
  busy.value = false
  if (result.ok) {
    toast.success(plural(locale.reminderSentOne, locale.reminderSent, data.value.recipients))
    open.value = false
    return
  }
  message.value = result.message
  await load()
}
</script>

<template>
  <PhModal v-model="open" :title="locale.reminderTitle">
    <div v-if="state === 'loading'" class="flex flex-col gap-3" :aria-label="locale.reminderLoading">
      <PhSkeleton :lines="3" />
    </div>
    <PhLoadError v-else-if="state === 'error'" :title="locale.genericError" @retry="load" />
    <div v-else-if="data" class="flex flex-col gap-4" data-testid="reminder-preview">
      <p v-if="data.recipients === 0">{{ locale.reminderNone }}</p>
      <p v-else>{{ plural(locale.reminderTextOne, locale.reminderText, data.recipients) }}</p>
      <section v-if="data.html" class="flex flex-col gap-2">
        <h3 class="text-xs font-semibold tracking-wide text-(--ph-ink-3) uppercase">{{ locale.reminderPreview }}</h3>
        <p class="text-sm font-medium">{{ locale.reminderSubject.replace('{assunto}', data.subject ?? '') }}</p>
        <iframe :srcdoc="data.html" sandbox="" :title="locale.reminderPreview" class="h-80 w-full rounded-(--radius-box) border border-(--ph-line) bg-white" />
      </section>
      <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
    </div>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ data?.recipients ? locale.back : locale.close }}</PhButton>
      <PhButton v-if="state === 'ready' && data && data.recipients > 0" :loading="busy" @click="confirm">{{ plural(locale.reminderConfirmOne, locale.reminderConfirm, data.recipients) }}</PhButton>
    </template>
  </PhModal>
</template>
