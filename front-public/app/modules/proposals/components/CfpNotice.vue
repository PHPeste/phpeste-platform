<script setup lang="ts">
import { computed } from 'vue'
import locale from '../locales/pt-br'
import { longDate } from '../composables/translate'
import type { CfpInfo } from '../types'

const props = defineProps<{ cfp: CfpInfo }>()
const title = computed(() => ({ closed: locale.closedTitle, scheduled: locale.scheduledTitle, draft: locale.draftTitle, open: '' })[props.cfp.status])
const text = computed(() => (props.cfp.status === 'scheduled' ? locale.scheduledText.replace('{data}', longDate(props.cfp.opensAt)) : locale.closedText))
</script>

<template>
  <section class="card border border-(--ph-line) bg-(--ph-surface) p-6" data-testid="cfp-closed">
    <h2 class="text-xl font-semibold">{{ title }}</h2>
    <p class="mt-2 text-(--ph-ink-2)">{{ text }}</p>
    <NuxtLink to="/palestrante/propostas" class="btn btn-ghost mt-4 self-start">{{ locale.goToMine }}</NuxtLink>
  </section>
</template>
