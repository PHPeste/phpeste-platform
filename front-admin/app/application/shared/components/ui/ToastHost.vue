<script setup lang="ts">
import { useToastStore } from '@/stores/toastStore'
import locale from '@/application/locales/pt-br/ui'

const store = useToastStore()

const tone = {
  success: 'alert-success',
  info: 'alert-info',
  warning: 'alert-warning',
  error: 'alert-error',
} as const
</script>

<template>
  <!-- Região viva: leitores de tela anunciam cada aviso novo sem tirar o foco -->
  <div
    class="toast toast-end toast-bottom z-50"
    role="region"
    :aria-label="locale.notifications"
    aria-live="polite"
  >
    <div
      v-for="toast in store.items"
      :key="toast.id"
      class="alert alert-soft flex items-center gap-3 text-sm"
      :class="tone[toast.kind]"
      :role="toast.kind === 'error' ? 'alert' : 'status'"
    >
      <span>{{ toast.message }}</span>
      <button
        v-if="toast.action"
        type="button"
        class="btn btn-ghost btn-sm underline"
        @click="toast.action.run(); store.dismiss(toast.id)"
      >
        {{ toast.action.label }}
      </button>
      <button type="button" class="btn btn-ghost btn-square btn-sm" @click="store.dismiss(toast.id)">
        <span aria-hidden="true">✕</span>
        <span class="sr-only">{{ locale.dismiss }}</span>
      </button>
    </div>
  </div>
</template>
