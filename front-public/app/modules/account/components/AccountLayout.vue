<script setup lang="ts">
import { computed } from 'vue'
import AuthPanel from '@/application/shared/components/AuthPanel.vue'
import { useAuthStore } from '@/stores/authStore'
import AccountNav from './AccountNav.vue'
import locale from '../locales/pt-br'

defineProps<{ title: string, intro?: string }>()
const auth = useAuthStore()
const isAuthenticated = computed(() => auth.isAuthenticated)
</script>

<template>
  <section class="mx-auto flex max-w-3xl flex-col gap-6">
    <div class="flex flex-col gap-2 print:hidden">
      <span class="text-xs font-medium tracking-wider text-(--ph-ink-3) uppercase">{{ locale.title }}</span>
      <h1 class="text-3xl leading-tight font-semibold">{{ title }}</h1>
      <p v-if="intro" class="text-(--ph-ink-2)">{{ intro }}</p>
    </div>
    <AuthPanel v-if="!isAuthenticated" :title="locale.authTitle" :text="locale.authText" />
    <template v-else>
      <AccountNav />
      <slot />
    </template>
  </section>
</template>
