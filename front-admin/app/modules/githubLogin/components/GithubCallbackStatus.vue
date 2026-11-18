<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useGithubCallback } from '../composables/useGithubCallback'
import locale from '../locales/pt-br'

const props = defineProps<{ code: string | null, state: string | null, providerError: string | null }>()
const emit = defineEmits<{ success: [] }>()

const { errorMessage, submit } = useGithubCallback()
const localError = ref<string | null>(null)

onMounted(async () => {
  if (props.providerError) {
    localError.value = locale.cancelled
    return
  }

  if (!props.code || !props.state) {
    localError.value = locale.missingParams
    return
  }

  if (await submit({ code: props.code, state: props.state })) {
    emit('success')
  }
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <template v-if="localError || errorMessage">
      <h1 class="text-2xl font-semibold">{{ locale.failedTitle }}</h1>
      <p role="alert" class="alert alert-error alert-soft text-sm">{{ localError ?? errorMessage }}</p>
      <PhButton to="/login" variant="ghost">{{ locale.backToLogin }}</PhButton>
    </template>
    <template v-else>
      <h1 class="text-2xl font-semibold">{{ locale.title }}</h1>
      <p role="status" aria-live="polite" class="text-(--ph-ink-2)">{{ locale.checking }}</p>
      <progress class="progress progress-primary w-full" :aria-label="locale.checking" />
    </template>
  </div>
</template>
