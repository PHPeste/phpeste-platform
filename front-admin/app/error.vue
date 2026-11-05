<script setup lang="ts">
import { computed } from 'vue'
import { FileQuestion, ServerCrash, ShieldAlert } from '@lucide/vue'
import type { NuxtError } from '#app'
import locale from '@/modules/shell/locales/pt-br'

const props = defineProps<{ error: NuxtError }>()

const code = computed<404 | 403 | 500>(() => (props.error.statusCode === 404 ? 404 : props.error.statusCode === 403 ? 403 : 500))
const text = computed(() => locale.errors[code.value])
const icon = computed(() => (code.value === 404 ? FileQuestion : code.value === 403 ? ShieldAlert : ServerCrash))

function goBack() {
  if (window.history.length > 1) clearError().then(() => window.history.back())
  else clearError({ redirect: '/' })
}
</script>

<template>
  <main class="flex min-h-dvh items-center justify-center bg-(--ph-bg) p-6">
    <div class="flex w-full max-w-md flex-col items-center gap-4 rounded-box border border-base-300 bg-base-100 p-8 text-center">
      <component :is="icon" class="size-10 text-(--ph-brand)" :stroke-width="1.5" aria-hidden="true" />
      <p class="text-sm font-medium text-(--ph-ink-3)">Erro {{ code }}</p>
      <h1 class="text-2xl font-semibold">{{ text.title }}</h1>
      <p class="text-sm text-(--ph-ink-2)">{{ text.description }}</p>
      <div class="flex flex-wrap justify-center gap-3">
        <PhButton variant="secondary" @click="goBack">{{ locale.errors.back }}</PhButton>
        <PhButton v-if="code === 500" @click="clearError({ redirect: $route.fullPath })">{{ locale.errors.reload }}</PhButton>
        <PhButton v-else @click="clearError({ redirect: '/' })">{{ locale.errors.home }}</PhButton>
      </div>
    </div>
  </main>
</template>
