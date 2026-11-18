<script setup lang="ts">
import { useCurrentEdition } from '@/application/shared/composables/useCurrentEdition'
import { useLogout } from '@/application/shared/composables/useLogout'
import locale from '@/application/locales/pt-br/site'
import { useAuthStore } from '@/stores/authStore'

const { edition, siteUrl, logo } = useCurrentEdition()
const auth = useAuthStore()
const { logout } = useLogout()
</script>

<template>
  <header class="border-b border-(--ph-line) bg-(--ph-surface)">
    <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-5 py-4">
      <NuxtLink to="/" class="flex items-center gap-3 text-(--ph-ink) no-underline" data-testid="site-brand">
        <img v-if="logo" :src="logo" :alt="edition?.name" class="h-9 max-w-48 object-contain">
        <b v-else class="text-lg font-semibold">{{ edition?.name }}</b>
      </NuxtLink>
      <nav class="flex items-center gap-4 text-sm" :aria-label="locale.account">
        <a v-if="siteUrl" :href="siteUrl" class="font-medium" data-testid="back-to-site">{{ locale.backToSite }}</a>
        <ClientOnly>
          <template v-if="auth.isAuthenticated">
            <NuxtLink to="/palestrante/propostas" class="font-medium">{{ locale.myProposals }}</NuxtLink>
            <span class="text-(--ph-ink-2)">{{ auth.user?.name }}</span>
            <button type="button" class="btn btn-ghost btn-sm" @click="logout">{{ locale.logout }}</button>
          </template>
          <NuxtLink v-else to="/login" class="btn btn-ghost btn-sm">{{ locale.login }}</NuxtLink>
        </ClientOnly>
      </nav>
    </div>
  </header>
</template>
