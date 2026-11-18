<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import type { EditionRole } from '@/modules/shell/types'
import locale from '../locales/pt-br'
import type { InviteInput } from '../types'

const props = defineProps<{ roleOptions: Array<{ value: string, label: string }>, submit: (input: InviteInput) => Promise<string | null> }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ sent: [email: string] }>()

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const form = reactive({ email: '', name: '', role: 'avaliador' as EditionRole })
const emailError = ref<string | null>(null)
const error = ref<string | null>(null)
const sending = ref(false)

watch(open, (value) => {
  if (!value) return
  Object.assign(form, { email: '', name: '', role: 'avaliador' })
  emailError.value = null
  error.value = null
})

async function send() {
  emailError.value = EMAIL.test(form.email.trim()) ? null : locale.emailInvalid
  if (emailError.value) return

  sending.value = true
  error.value = await props.submit({ email: form.email.trim(), name: form.name.trim(), role: form.role })
  sending.value = false

  if (!error.value) {
    emit('sent', form.email.trim())
    open.value = false
  }
}
</script>

<template>
  <PhModal v-model="open" :title="locale.inviteTitle">
    <form id="invite-form" class="flex flex-col gap-4" novalidate @submit.prevent="send">
      <PhInput v-model="form.email" type="email" :label="locale.email" autocomplete="off" required :error="emailError" />
      <PhInput v-model="form.name" :label="locale.name" :hint="locale.nameHint" autocomplete="off" />
      <PhSelect v-model="form.role" :label="locale.role" :options="roleOptions" />
      <p v-if="error" role="alert" class="alert alert-error alert-soft text-sm">{{ error }}</p>
    </form>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
      <PhButton type="submit" form="invite-form" :loading="sending">{{ locale.sendInvite }}</PhButton>
    </template>
  </PhModal>
</template>
