<script setup lang="ts">
import { reactive, ref } from 'vue'
import TextField from './TextField.vue'
import { useSpeakerAuth } from '../composables/useSpeakerAuth'
import locale from '../locales/pt-br'

defineProps<{ title: string, text?: string }>()

const { login, register } = useSpeakerAuth()
const forgotUrl = `${String(useRuntimeConfig().public.adminUrl).replace(/\/$/, '')}/esqueci-senha`
const mode = ref<'login' | 'register'>('login')
const form = reactive({ name: '', email: '', password: '', passwordConfirmation: '' })
const localError = ref<string | null>(null)

const fieldError = (field: string) => (mode.value === 'login' ? login.fieldErrors.value : register.fieldErrors.value)?.[field]?.[0] ?? null

async function submit() {
  localError.value = null
  if (mode.value === 'register' && form.password !== form.passwordConfirmation) {
    localError.value = locale.passwordMismatch
    return
  }
  if (mode.value === 'login') await login.submit({ email: form.email.trim(), password: form.password })
  else await register.submit({ name: form.name.trim(), email: form.email.trim(), password: form.password, passwordConfirmation: form.passwordConfirmation })
}
</script>

<template>
  <section class="card border border-(--ph-line) bg-(--ph-surface) p-6" data-testid="auth-panel">
    <div class="flex flex-col gap-4">
      <div class="flex flex-col gap-1">
        <h2 class="text-xl font-semibold">{{ title }}</h2>
        <p v-if="text" class="text-sm text-(--ph-ink-2)">{{ text }}</p>
      </div>
      <div role="tablist" class="tabs tabs-border" :aria-label="title">
        <button type="button" role="tab" class="tab" :class="{ 'tab-active': mode === 'login' }" :aria-selected="mode === 'login'" @click="mode = 'login'">{{ locale.login }}</button>
        <button type="button" role="tab" class="tab" :class="{ 'tab-active': mode === 'register' }" :aria-selected="mode === 'register'" @click="mode = 'register'">{{ locale.register }}</button>
      </div>
      <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
        <TextField v-if="mode === 'register'" v-model="form.name" :label="locale.name" autocomplete="name" required :error="fieldError('name')" />
        <TextField v-model="form.email" type="email" :label="locale.email" autocomplete="email" required :error="fieldError('email')" />
        <TextField v-model="form.password" type="password" :label="locale.password" :hint="mode === 'register' ? locale.passwordHint : undefined" :autocomplete="mode === 'login' ? 'current-password' : 'new-password'" required :error="fieldError('password')" />
        <TextField v-if="mode === 'register'" v-model="form.passwordConfirmation" type="password" :label="locale.passwordConfirmation" autocomplete="new-password" required />
        <p v-if="localError || (mode === 'login' ? login.errorMessage.value : register.errorMessage.value)" role="alert" class="alert alert-error alert-soft text-sm">
          {{ localError ?? (mode === 'login' ? login.errorMessage.value : register.errorMessage.value) }}
        </p>
        <div class="flex flex-wrap items-center gap-4">
          <button type="submit" class="btn btn-primary" :disabled="login.isSubmitting.value || register.isSubmitting.value">
            {{ mode === 'login' ? locale.login : locale.register }}
          </button>
          <a v-if="mode === 'login'" :href="forgotUrl" class="text-sm">{{ locale.forgot }}</a>
        </div>
      </form>
    </div>
  </section>
</template>
