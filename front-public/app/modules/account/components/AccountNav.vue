<script setup lang="ts">
import locale from '../locales/pt-br'

const route = useRoute()
const links = [
  { to: '/conta/ingressos', label: locale.navTickets },
  { to: '/conta/pedidos', label: locale.navOrders },
  { to: '/palestrante/propostas', label: locale.navProposals },
]
const current = (to: string) => route.path === to || route.path.startsWith(`${to}/`)
</script>

<template>
  <nav :aria-label="locale.nav" class="tabs tabs-border print:hidden">
    <NuxtLink v-for="link in links" :key="link.to" :to="link.to" class="tab" :class="{ 'tab-active': current(link.to) }" :aria-current="current(link.to) ? 'page' : undefined">{{ link.label }}</NuxtLink>
  </nav>
</template>
