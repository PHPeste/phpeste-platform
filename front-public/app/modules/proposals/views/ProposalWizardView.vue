<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useCurrentEdition } from '@/application/shared/composables/useCurrentEdition'
import ProposalWizard from '../components/ProposalWizard.vue'
import locale from '../locales/pt-br'

const route = useRoute()
const { edition } = useCurrentEdition()
const editId = computed(() => (typeof route.query.proposta === 'string' ? route.query.proposta : null))

useHead({ title: locale.title })
</script>

<template>
  <ProposalWizard v-if="edition" :key="`${edition.id}-${editId}`" :edition-id="edition.id" :edit-id="editId" />
</template>
