<script setup lang="ts">
import { ref, watch } from 'vue'
import locale from '@/modules/shell/locales/pt-br'

const menuOpen = ref(false)
const route = useRoute()
watch(() => route.fullPath, () => { menuOpen.value = false })
</script>

<template>
  <div class="min-h-dvh bg-(--ph-bg)">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded-lg focus:bg-base-100 focus:px-4 focus:py-2">{{ locale.skipToContent }}</a>
    <div class="flex min-h-dvh">
      <aside class="hidden w-[264px] shrink-0 lg:block">
        <div class="sticky top-0 h-dvh overflow-y-auto"><SideNav /></div>
      </aside>
      <div class="flex min-w-0 flex-1 flex-col">
        <AppTopbar @open-menu="menuOpen = true" />
        <main id="conteudo" tabindex="-1" class="flex-1 px-4 py-6 focus:outline-none lg:px-10">
          <slot />
        </main>
      </div>
    </div>
    <MobileMenu v-model="menuOpen" />
    <PhToastHost />
  </div>
</template>
