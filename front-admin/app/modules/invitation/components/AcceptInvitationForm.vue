<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import authService from '@/application/shared/services/authService'
import { formatInvitationDate, useInvitation } from '../composables/useInvitation'
import locale from '../locales/pt-br'

const props = defineProps<{ token: string }>()
const emit = defineEmits<{ accepted: [] }>()

const authStore = useAuthStore()
const { state, invitation, goneMessage, isSubmitting, errorMessage, fieldErrors, load, accept } = useInvitation()

const form = reactive({ name: '', password: '', confirmation: '' })
const submitted = ref(false)

onMounted(async () => {
  await load(props.token)
  form.name = invitation.value?.name ?? ''
})

const signedInEmail = computed(() => authStore.user?.email ?? null)
const signedInWithInvitedEmail = computed(() =>
  !!signedInEmail.value && !!invitation.value && signedInEmail.value.toLowerCase() === invitation.value.email.toLowerCase(),
)
const signedInWithOtherEmail = computed(() => authStore.isAuthenticated && !!invitation.value && !signedInWithInvitedEmail.value)
const creatingAccount = computed(() => !authStore.isAuthenticated && !!invitation.value && !invitation.value.accountExists)

const nameError = computed(() => {
  if (fieldErrors.value?.name) return fieldErrors.value.name[0] ?? null
  return submitted.value && creatingAccount.value && !form.name.trim() ? locale.nameRequired : null
})
const passwordError = computed(() => {
  if (fieldErrors.value?.password) return fieldErrors.value.password[0] ?? null
  if (!submitted.value || authStore.isAuthenticated) return null
  if (creatingAccount.value) return form.password.length < 10 ? locale.passwordTooShort : null
  return form.password ? null : locale.passwordRequired
})
const confirmationError = computed(() =>
  submitted.value && creatingAccount.value && form.confirmation !== form.password ? locale.passwordMismatch : null,
)

async function switchAccount() {
  await authService.logout().catch(() => undefined)
  authStore.clearSession()
}

const fill = (text: string, values: Record<string, string>) => text.replace(/\{(\w+)\}/g, (_, key: string) => values[key] ?? '')

async function onSubmit() {
  submitted.value = true
  if (nameError.value || passwordError.value || confirmationError.value) return

  const ok = await accept(
    authStore.isAuthenticated
      ? { token: props.token }
      : creatingAccount.value
        ? { token: props.token, name: form.name.trim(), password: form.password, passwordConfirmation: form.confirmation }
        : { token: props.token, password: form.password },
  )

  if (ok) emit('accepted')
}
</script>

<template>
  <p v-if="state === 'loading'" role="status" aria-live="polite" class="flex items-center gap-2 text-(--ph-ink-2)">
    <span class="loading loading-spinner loading-sm" aria-hidden="true" />
    {{ locale.loading }}
  </p>

  <div v-else-if="state === 'not_found' || state === 'gone' || state === 'error'" class="flex flex-col gap-4">
    <h1 class="text-2xl font-semibold">{{ state === 'not_found' ? locale.notFoundTitle : locale.goneTitle }}</h1>
    <p role="alert" class="alert alert-error alert-soft text-sm">
      {{ state === 'not_found' ? locale.notFound : (goneMessage ?? locale.genericError) }}
    </p>
    <p v-if="state === 'gone'" class="text-sm text-(--ph-ink-2)">{{ locale.askNew }}</p>
    <PhButton to="/login" variant="ghost">{{ locale.backToLogin }}</PhButton>
  </div>

  <form v-else-if="invitation" class="flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
    <div class="flex flex-col gap-1.5">
      <h1 class="text-2xl leading-tight font-semibold">{{ locale.title }}</h1>
      <p class="text-(--ph-ink-2)">
        {{ invitation.invitedBy ? fill(locale.invitedBy, { nome: invitation.invitedBy }) : locale.invitedAnonymous }}
        <b class="font-medium text-(--ph-ink)">{{ invitation.roleLabel }}</b>
        {{ locale.in }} {{ invitation.editionName }}.
      </p>
    </div>

    <template v-if="signedInWithOtherEmail">
      <p role="alert" class="alert alert-warning alert-soft text-sm">
        {{ fill(locale.wrongAccount, { email: signedInEmail ?? '', convite: invitation.email }) }}
      </p>
      <PhButton variant="ghost" @click="switchAccount">{{ locale.signOut }}</PhButton>
    </template>

    <template v-else>
      <p v-if="signedInWithInvitedEmail" class="text-sm text-(--ph-ink-2)">{{ fill(locale.signedInAs, { email: invitation.email }) }}</p>

      <template v-else>
        <PhInput
          v-if="creatingAccount"
          id="invitation-name"
          v-model="form.name"
          autocomplete="name"
          :label="locale.nameLabel"
          :error="nameError"
        />

        <div class="flex flex-col gap-1.5">
          <label for="invitation-email" class="text-sm font-medium">{{ locale.emailLabel }}</label>
          <input id="invitation-email" :value="invitation.email" type="email" class="input w-full" readonly aria-describedby="invitation-email-hint">
          <p id="invitation-email-hint" class="text-xs text-(--ph-ink-3)">
            {{ creatingAccount ? locale.emailHint : locale.existingHint }}
          </p>
        </div>

        <PhInput
          id="invitation-password"
          v-model="form.password"
          type="password"
          :autocomplete="creatingAccount ? 'new-password' : 'current-password'"
          :label="creatingAccount ? locale.createPassword : locale.existingPassword"
          :placeholder="creatingAccount ? locale.passwordPlaceholder : undefined"
          :error="passwordError"
        />

        <PhInput
          v-if="creatingAccount"
          id="invitation-password-confirmation"
          v-model="form.confirmation"
          type="password"
          autocomplete="new-password"
          :label="locale.confirmationLabel"
          :error="confirmationError"
        />

        <NuxtLink v-if="!creatingAccount" to="/esqueci-senha" class="text-sm text-(--ph-link) hover:text-(--ph-brand)">
          {{ locale.forgotPassword }}
        </NuxtLink>
      </template>

      <div v-if="errorMessage && !fieldErrors" role="alert" class="alert alert-error alert-soft text-sm">{{ errorMessage }}</div>

      <PhButton type="submit" block :loading="isSubmitting">
        {{ authStore.isAuthenticated ? locale.submitSignedIn : locale.submit }}
      </PhButton>
    </template>

    <p class="text-center text-xs text-(--ph-ink-3)">{{ fill(locale.validUntil, { data: formatInvitationDate(invitation.expiresAt) }) }}</p>
  </form>
</template>
