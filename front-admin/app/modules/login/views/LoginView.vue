<script setup lang="ts">
import LoginBrandPanel from '../components/LoginBrandPanel.vue'
import LoginForm from '../components/LoginForm.vue'
import { safeRedirect } from '@/application/shared/layout/redirect'

const route = useRoute()

function onSuccess() {
  navigateTo(safeRedirect(route.query.redirect))
}
</script>

<template>
  <div class="flex min-h-dvh flex-wrap bg-(--ph-bg)">
    <LoginBrandPanel />
    <section class="flex flex-1 basis-120 items-center justify-center px-6 py-12">
      <LoginForm @success="onSuccess" />
    </section>
  </div>
</template>
