<script setup lang="ts">
import { Bell, Menu, Search } from '@lucide/vue'
import { useId } from 'vue'
import { useEdition } from '@/application/shared/composables/useEdition'
import locale from '@/modules/shell/locales/pt-br'

const emit = defineEmits<{ 'open-menu': [] }>()

const { current } = useEdition()
const searchId = `ph-${useId()}-search`
</script>

<template>
  <header class="flex flex-wrap items-center gap-3 border-b border-base-300 bg-(--ph-bg) px-4 py-3.5 lg:gap-4 lg:px-10">
    <button type="button" class="ph-topbtn lg:hidden" :aria-label="locale.openMenu" @click="emit('open-menu')">
      <Menu class="size-5" :stroke-width="1.75" aria-hidden="true" />
    </button>

    <EditionSwitcher />

    <form role="search" class="relative order-last w-full flex-[1_1_320px] lg:order-none lg:max-w-[520px]" @submit.prevent>
      <Search class="pointer-events-none absolute left-3.5 top-3 size-5 text-(--ph-ink-3)" :stroke-width="1.75" aria-hidden="true" />
      <label :for="searchId" class="sr-only">{{ locale.search }}</label>
      <input :id="searchId" type="search" class="input w-full pl-10" :placeholder="locale.searchPlaceholder">
    </form>

    <div class="ml-auto flex items-center gap-3">
      <a
        v-if="current?.siteUrl"
        :href="current.siteUrl"
        class="ph-topbtn hidden px-4 sm:inline-flex"
        target="_blank"
        rel="noopener noreferrer"
      >{{ locale.viewSite }}</a>
      <button type="button" class="ph-topbtn" :aria-label="locale.notifications">
        <Bell class="size-5" :stroke-width="1.75" aria-hidden="true" />
      </button>
      <UserMenu />
    </div>
  </header>
</template>
