<script setup lang="ts">
import { useCurrentEdition } from '@/application/shared/composables/useCurrentEdition'
import MyProposals from '../components/MyProposals.vue'
import locale from '../locales/pt-br'

const { edition } = useCurrentEdition()
useHead({ title: locale.mineTitle })
</script>

<template>
  <MyProposals v-if="edition" :edition-id="edition.id" />
</template>
