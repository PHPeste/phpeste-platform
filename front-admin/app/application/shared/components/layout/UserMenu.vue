<script setup lang="ts">
import { LogOut } from '@lucide/vue'
import { useAuthStore } from '@/stores/authStore'
import { useEdition } from '@/application/shared/composables/useEdition'
import { useLogout } from '@/application/shared/composables/useLogout'
import locale from '@/modules/shell/locales/pt-br'

const auth = useAuthStore()
const { role } = useEdition()
const { logout } = useLogout()

const initials = computed(() =>
  (auth.user?.name ?? '').split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]!.toUpperCase()).join(''),
)
</script>

<template>
  <MenuPopover :label="locale.userMenu" align="end">
    <template #trigger>
      <span class="flex size-8 items-center justify-center rounded-full bg-(--ph-accent) text-xs font-semibold text-(--ph-ink)" aria-hidden="true">{{ initials }}</span>
      <span class="hidden flex-col text-left leading-tight sm:flex">
        <span class="text-[13px] font-medium">{{ auth.user?.name }}</span>
        <span class="text-[11px] font-normal text-(--ph-ink-3)">{{ role ? locale.roles[role] : '' }}</span>
      </span>
    </template>
    <p class="px-3 py-2 text-xs text-(--ph-ink-2)">{{ auth.user?.email }}</p>
    <button type="button" class="flex min-h-11 w-full items-center gap-3 rounded-lg px-3 text-left text-sm hover:bg-base-200" @click="logout">
      <LogOut class="size-4" aria-hidden="true" />
      {{ locale.logout }}
    </button>
  </MenuPopover>
</template>
