<script setup lang="ts">
import { useAuthStore } from '@/stores/authStore'
import { useLogout } from '@/application/shared/composables/useLogout'
import locale from '../locales/pt-br'

const authStore = useAuthStore()
const { logout } = useLogout()
</script>

<template>
  <header class="flex flex-wrap items-center justify-between gap-4 border-b border-(--ph-line) bg-(--ph-surface) px-5 py-5 md:px-10">
    <div class="flex items-center gap-3">
      <span class="flex size-10 items-center justify-center rounded-[10px] bg-(--ph-brand) font-semibold text-(--ph-on-brand)" aria-hidden="true">Ph</span>
      <span class="text-base font-semibold">{{ locale.brandName }}</span>
    </div>
    <div class="flex items-center gap-3">
      <span class="text-(--ph-ink-2)">{{ authStore.user?.name }}</span>
      <PhButton variant="ghost" @click="logout">{{ locale.signOut }}</PhButton>
    </div>
  </header>
</template>
