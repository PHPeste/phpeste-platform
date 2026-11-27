<script setup lang="ts">
import { nextTick, ref, useId } from 'vue'
import TextField from '@/application/shared/components/TextField.vue'
import { transferErrors } from '../composables/useTicket'
import locale from '../locales/pt-br'

const props = defineProps<{ currentEmail: string, busy: boolean, errors: Record<string, string>, message: string | null }>()
const emit = defineEmits<{ transfer: [name: string, email: string] }>()
const id = useId()
const name = ref('')
const email = ref('')
const local = ref<Record<string, string>>({})
const dialog = ref<HTMLDialogElement | null>(null)

async function ask() {
  local.value = transferErrors(name.value, email.value, props.currentEmail)
  if (Object.keys(local.value).length) return
  await nextTick()
  dialog.value?.showModal()
}

function confirm() {
  dialog.value?.close()
  emit('transfer', name.value, email.value)
}

const error = (field: string) => local.value[field] ?? props.errors[field] ?? null
</script>

<template>
  <section class="card flex flex-col gap-4 border border-(--ph-line) bg-(--ph-surface) p-6 print:hidden" :aria-labelledby="`${id}-title`">
    <div class="flex flex-col gap-1">
      <h2 :id="`${id}-title`" class="text-lg font-semibold">{{ locale.transferTitle }}</h2>
      <p class="text-sm text-(--ph-ink-2)">{{ locale.transferIntro }}</p>
    </div>
    <form class="flex flex-col gap-4" novalidate @submit.prevent="ask">
      <TextField v-model="name" :label="locale.transferName" required :maxlength="120" autocomplete="off" :error="error('name')" />
      <TextField v-model="email" type="email" :label="locale.transferEmail" required autocomplete="off" :error="error('email')" />
      <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
      <div>
        <button type="submit" class="btn btn-outline btn-error" :disabled="busy">{{ locale.transferSubmit }}</button>
      </div>
    </form>
    <dialog ref="dialog" class="modal" :aria-labelledby="`${id}-confirm`">
      <div class="modal-box flex flex-col gap-4">
        <h2 :id="`${id}-confirm`" class="text-lg font-semibold">{{ locale.transferConfirmTitle }}</h2>
        <p class="text-sm">{{ locale.transferConfirmText.replace('{nome}', name.trim()).replace('{email}', email.trim()) }}</p>
        <div class="modal-action">
          <button type="button" class="btn btn-ghost" @click="dialog?.close()">{{ locale.cancel }}</button>
          <button type="button" class="btn btn-error" :disabled="busy" @click="confirm">{{ locale.transferConfirm }}</button>
        </div>
      </div>
    </dialog>
  </section>
</template>
