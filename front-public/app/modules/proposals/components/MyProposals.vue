<script setup lang="ts">
import { onMounted, watch } from 'vue'
import AuthPanel from './AuthPanel.vue'
import { useMyProposals } from '../composables/useMyProposals'
import { useSpeakerAuth } from '../composables/useSpeakerAuth'
import locale from '../locales/pt-br'

const props = defineProps<{ editionId: string }>()
const { proposals, state, load } = useMyProposals(props.editionId)
const { isAuthenticated } = useSpeakerAuth()

onMounted(load)
watch(isAuthenticated, (value) => { if (value) load() })

const tone = (status: string, decision: string | null) => (decision === 'accepted' ? 'badge-success' : status === 'submitted' ? 'badge-info' : status === 'withdrawn' ? 'badge-ghost' : 'badge-warning')
</script>

<template>
  <section class="mx-auto flex max-w-3xl flex-col gap-6">
    <h1 class="text-3xl leading-tight font-semibold">{{ locale.mineTitle }}</h1>
    <AuthPanel v-if="!isAuthenticated" :title="locale.mineAuth" />
    <div v-else-if="state === 'loading' || state === 'idle'" class="flex flex-col gap-3" role="status" aria-busy="true">
      <div v-for="n in 3" :key="n" class="skeleton h-16 w-full" />
    </div>
    <div v-else-if="state === 'error'" role="alert" class="alert alert-error alert-soft flex flex-wrap justify-between gap-3">
      <span>{{ locale.loadError }}</span>
      <button type="button" class="btn btn-sm" @click="load">{{ locale.retry }}</button>
    </div>
    <div v-else-if="!proposals.length" class="card flex flex-col items-start gap-3 border border-(--ph-line) bg-(--ph-surface) p-6">
      <h2 class="text-lg font-semibold">{{ locale.mineEmpty }}</h2>
      <p class="text-sm text-(--ph-ink-2)">{{ locale.mineEmptyHint }}</p>
      <NuxtLink to="/cfp" class="btn btn-primary">{{ locale.submit }}</NuxtLink>
    </div>
    <ul v-else class="flex flex-col gap-3" :aria-label="locale.mineTitle">
      <li v-for="proposal in proposals" :key="proposal.id" class="card flex flex-row flex-wrap items-center justify-between gap-3 border border-(--ph-line) bg-(--ph-surface) p-5">
        <div class="flex min-w-0 flex-col gap-1">
          <span class="text-xs text-(--ph-ink-3)">{{ locale.proposalNumber.replace('{n}', String(proposal.number)) }}<template v-if="proposal.track"> · {{ proposal.track }}</template></span>
          <b class="font-medium">{{ proposal.title }}</b>
          <span class="flex flex-wrap gap-2">
            <span class="badge badge-soft" :class="tone(proposal.status, proposal.decision)">{{ proposal.decisionLabel ?? proposal.statusLabel }}</span>
            <span v-if="proposal.confirmation" class="badge badge-soft badge-ghost">{{ locale.confirmationStatus.replace('{status}', proposal.confirmation.statusLabel) }}</span>
            <span v-if="!proposal.isOwner" class="badge badge-soft badge-ghost">{{ locale.coauthor }}</span>
          </span>
        </div>
        <div class="flex gap-2">
          <NuxtLink v-if="proposal.status === 'draft'" :to="`/cfp?proposta=${proposal.id}`" class="btn btn-primary btn-sm">{{ locale.continueDraft }}</NuxtLink>
          <NuxtLink :to="`/palestrante/propostas/${proposal.id}`" class="btn btn-ghost btn-sm" :aria-label="`${locale.openProposal} ${proposal.title}`">{{ locale.openProposal }}</NuxtLink>
        </div>
      </li>
    </ul>
  </section>
</template>
