<script setup lang="ts">
import { computed, reactive } from 'vue'
import { useResetPassword } from '../composables/useResetPassword'
import locale from '../locales/pt-br'

const props = defineProps<{ email: string | null, token: string | null }>()

const form = reactive({ password: '', confirmation: '' })
const touched = reactive({ password: false, confirmation: false })
const { isSubmitting, done, invalidLink, errorMessage, passwordError, submit } = useResetPassword()

const linkIsMissing = computed(() => !props.email || !props.token)

const localPasswordError = computed(() => {
  if (!touched.password) return passwordError.value
  return form.password.length < 10 ? locale.passwordTooShort : passwordError.value
})

const confirmationError = computed(() => {
  if (!touched.confirmation) return null
  return form.confirmation === form.password ? null : locale.passwordMismatch
})

async function onSubmit() {
  touched.password = true
  touched.confirmation = true
  if (localPasswordError.value || confirmationError.value || !props.email || !props.token) return

  await submit({ email: props.email, token: props.token, password: form.password, passwordConfirmation: form.confirmation })
}
</script>

<template>
  <div v-if="done" class="flex flex-col gap-4" role="status" aria-live="polite">
    <h1 class="text-2xl font-semibold">{{ locale.doneTitle }}</h1>
    <p class="text-(--ph-ink-2)">{{ locale.done }}</p>
    <PhButton to="/login">{{ locale.goToLogin }}</PhButton>
  </div>

  <div v-else-if="invalidLink || linkIsMissing" class="flex flex-col gap-4">
    <h1 class="text-2xl font-semibold">{{ locale.invalidLinkTitle }}</h1>
    <p role="alert" class="alert alert-error alert-soft text-sm">{{ locale.invalidLink }}</p>
    <PhButton to="/esqueci-senha" variant="ghost">{{ locale.requestNew }}</PhButton>
  </div>

  <form v-else class="flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
    <div class="flex flex-col gap-1.5">
      <h1 class="text-2xl font-semibold">{{ locale.title }}</h1>
      <p class="text-(--ph-ink-2)">{{ locale.subtitle }}</p>
    </div>

    <PhInput
      id="new-password"
      v-model="form.password"
      type="password"
      autocomplete="new-password"
      :label="locale.passwordLabel"
      :placeholder="locale.passwordPlaceholder"
      :error="localPasswordError"
    />
    <PhInput
      id="new-password-confirmation"
      v-model="form.confirmation"
      type="password"
      autocomplete="new-password"
      :label="locale.confirmationLabel"
      :error="confirmationError"
    />

    <div v-if="errorMessage" role="alert" class="alert alert-error alert-soft text-sm">{{ errorMessage }}</div>

    <PhButton type="submit" block :loading="isSubmitting">{{ locale.submit }}</PhButton>
  </form>
</template>
