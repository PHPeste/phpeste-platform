<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForgotPassword } from '../composables/useForgotPassword'
import locale from '../locales/pt-br'

const email = ref('')
const touched = ref(false)
const { isSubmitting, sent, errorMessage, submit } = useForgotPassword()

const emailError = computed(() => {
  if (!touched.value) return null
  if (!email.value.trim()) return locale.emailRequired
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim()) ? null : locale.emailInvalid
})

async function onSubmit() {
  touched.value = true
  if (emailError.value) return
  await submit(email.value.trim())
}
</script>

<template>
  <div v-if="sent" class="flex flex-col gap-4" role="status" aria-live="polite">
    <h1 class="text-2xl font-semibold">{{ locale.sentTitle }}</h1>
    <p class="text-(--ph-ink-2)">{{ locale.sent }}</p>
    <PhButton to="/login" variant="ghost">{{ locale.backToLogin }}</PhButton>
  </div>

  <form v-else class="flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
    <div class="flex flex-col gap-1.5">
      <h1 class="text-2xl font-semibold">{{ locale.title }}</h1>
      <p class="text-(--ph-ink-2)">{{ locale.subtitle }}</p>
    </div>

    <PhInput
      id="email"
      v-model="email"
      type="email"
      autocomplete="email"
      :label="locale.emailLabel"
      :placeholder="locale.emailPlaceholder"
      :error="emailError"
    />

    <div v-if="errorMessage" role="alert" class="alert alert-error alert-soft text-sm">{{ errorMessage }}</div>

    <PhButton type="submit" block :loading="isSubmitting">{{ locale.submit }}</PhButton>
    <NuxtLink to="/login" class="text-center text-sm text-(--ph-link) hover:text-(--ph-brand)">{{ locale.backToLogin }}</NuxtLink>
  </form>
</template>
