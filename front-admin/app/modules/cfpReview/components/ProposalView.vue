<script setup lang="ts">
import { computed } from 'vue'
import locale from '../locales/pt-br'
import type { ProposalDetail } from '../types'

const props = defineProps<{ proposal: ProposalDetail }>()

const meta = computed(() => [
  locale.proposalHeading.replace('{n}', String(props.proposal.number)),
  props.proposal.track,
  props.proposal.durationMinutes ? locale.minutes.replace('{n}', String(props.proposal.durationMinutes)) : props.proposal.format,
].filter(Boolean).join(' · '))
</script>

<template>
  <article class="flex flex-col gap-4">
    <div class="flex flex-col gap-2">
      <span class="text-xs font-medium tracking-wide text-(--ph-ink-3) uppercase">{{ meta }}</span>
      <h2 class="text-2xl leading-tight font-semibold">{{ proposal.title }}</h2>
      <div class="flex flex-wrap items-center gap-2">
        <PhBadge v-if="proposal.nordeste" status="info">{{ locale.nordeste }}</PhBadge>
        <PhBadge v-if="proposal.level" :dot="false">{{ proposal.level }}</PhBadge>
        <PhBadge v-if="proposal.speakers === null" :dot="false">{{ locale.speakerHidden }}</PhBadge>
        <span v-else class="text-sm text-(--ph-ink-2)">{{ locale.speakers }}: {{ proposal.speakers.join(', ') }}</span>
      </div>
    </div>
    <section class="flex flex-col gap-1">
      <h3 class="text-sm font-semibold">{{ locale.summaryField }}</h3>
      <p class="whitespace-pre-line text-(--ph-ink-2)">{{ proposal.summary }}</p>
    </section>
    <section v-if="proposal.audience" class="flex flex-col gap-1">
      <h3 class="text-sm font-semibold">{{ locale.audience }}</h3>
      <p class="whitespace-pre-line text-(--ph-ink-2)">{{ proposal.audience }}</p>
    </section>
  </article>
</template>
