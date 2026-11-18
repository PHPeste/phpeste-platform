<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { Eye, EyeOff } from '@lucide/vue'
import { useGithubLogin, useLogin } from '../composables/useLogin'
import locale from '../locales/pt-br'

const emit = defineEmits<{ success: [] }>()

const form = reactive({ email: '', password: '', remember: true })
const touched = reactive({ email: false, password: false })
const showPassword = ref(false)

const { isSubmitting, errorMessage, submit } = useLogin()
const { isRedirecting, githubError, start: startGithub } = useGithubLogin()

const emailError = computed(() => {
  if (!touched.email) {
    return null
  }
  if (!form.email.trim()) {
    return locale.emailRequired
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
    return locale.emailInvalid
  }
  return null
})

const passwordError = computed(() => {
  if (!touched.password) {
    return null
  }
  return form.password ? null : locale.passwordRequired
})

const isValid = computed(
  () =>
    form.email.trim() !== '' &&
    form.password !== '' &&
    !emailError.value &&
    !passwordError.value
)

async function onSubmit() {
  touched.email = true
  touched.password = true

  if (!isValid.value) {
    return
  }

  const success = await submit({ email: form.email.trim(), password: form.password, remember: form.remember })

  if (success) {
    emit('success')
  }
}
</script>

<template>
  <div class="flex w-full max-w-100 flex-col gap-5">
    <div class="flex flex-col gap-1.5">
      <h1 class="text-3xl font-semibold">{{ locale.title }}</h1>
      <p class="text-(--ph-ink-2)">{{ locale.subtitle }}</p>
    </div>

    <form class="flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
      <div class="flex flex-col gap-1.5">
        <label for="email" class="text-sm font-medium">{{ locale.emailLabel }}</label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          autocomplete="email"
          :placeholder="locale.emailPlaceholder"
          :aria-invalid="emailError ? 'true' : undefined"
          :aria-describedby="emailError ? 'email-error' : undefined"
          class="input w-full"
          :class="{ 'input-error': emailError }"
          @blur="touched.email = true"
        >
        <p v-if="emailError" id="email-error" role="alert" class="text-sm text-error">{{ emailError }}</p>
      </div>

      <div class="flex flex-col gap-1.5">
        <div class="flex items-baseline justify-between">
          <label for="password" class="text-sm font-medium">{{ locale.passwordLabel }}</label>
          <NuxtLink to="/esqueci-senha" class="text-xs text-(--ph-link) hover:text-(--ph-brand)">{{ locale.forgotPassword }}</NuxtLink>
        </div>
        <div class="input w-full" :class="{ 'input-error': passwordError }">
          <input
            id="password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="current-password"
            :placeholder="locale.passwordPlaceholder"
            :aria-invalid="passwordError ? 'true' : undefined"
            :aria-describedby="passwordError ? 'password-error' : undefined"
            class="grow"
            @blur="touched.password = true"
          >
          <button
            type="button"
            class="btn btn-ghost btn-square btn-sm"
            :aria-label="showPassword ? locale.hidePassword : locale.showPassword"
            :aria-pressed="showPassword"
            @click="showPassword = !showPassword"
          >
            <EyeOff v-if="showPassword" :size="18" :stroke-width="1.75" aria-hidden="true" />
            <Eye v-else :size="18" :stroke-width="1.75" aria-hidden="true" />
          </button>
        </div>
        <p v-if="passwordError" id="password-error" role="alert" class="text-sm text-error">{{ passwordError }}</p>
      </div>

      <PhCheckbox id="remember" v-model="form.remember" :label="locale.remember" />

      <div v-if="errorMessage" role="alert" class="alert alert-error alert-soft text-sm">
        {{ errorMessage }}
      </div>

      <PhButton type="submit" block :loading="isSubmitting">
        {{ isSubmitting ? locale.submitting : locale.submit }}
      </PhButton>
    </form>

    <div class="flex items-center gap-3" aria-hidden="true">
      <span class="h-px flex-1 bg-(--ph-line)" />
      <span class="text-xs text-(--ph-ink-3)">{{ locale.or }}</span>
      <span class="h-px flex-1 bg-(--ph-line)" />
    </div>

    <PhButton variant="ghost" block :loading="isRedirecting" @click="startGithub">
      {{ isRedirecting ? locale.githubRedirecting : locale.github }}
    </PhButton>
    <p v-if="githubError" role="alert" class="text-sm text-error">{{ githubError }}</p>

    <p class="text-center text-xs text-(--ph-ink-3)">{{ locale.noAccess }}</p>
  </div>
</template>
