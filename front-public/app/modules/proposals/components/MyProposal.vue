<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import AuthPanel from '@/application/shared/components/AuthPanel.vue'
import TextAreaField from './TextAreaField.vue'
import { longDate } from '../composables/translate'
import { useCfp } from '../composables/useCfp'
import { useMyProposal } from '../composables/useMyProposals'
import { useSiteAuth } from '@/application/shared/composables/useSiteAuth'
import locale from '../locales/pt-br'

const props = defineProps<{ editionId: string, id: string }>()
const detail = useMyProposal(props.editionId, props.id)
const { proposal, state, busy } = detail
const { cfp, load: loadCfp } = useCfp(props.editionId)
const { isAuthenticated } = useSiteAuth()
const notice = ref<string | null>(null)
const error = ref<string | null>(null)
const dialog = ref<'withdraw' | 'decline' | null>(null)
const reason = ref('')

onMounted(() => Promise.all([detail.load(), loadCfp()]))
watch(isAuthenticated, (value) => { if (value) detail.load() })

async function run(action: () => Promise<{ ok: boolean, message?: string }>, success: string) {
  error.value = null
  const result = await action()
  dialog.value = null
  if (result.ok) notice.value = success
  else error.value = result.message ?? locale.genericError
}
</script>

<template>
  <section class="mx-auto flex max-w-3xl flex-col gap-6">
    <NuxtLink to="/palestrante/propostas" class="text-sm">← {{ locale.mineTitle }}</NuxtLink>
    <AuthPanel v-if="!isAuthenticated" :title="locale.mineAuth" />
    <div v-else-if="state === 'loading' || state === 'idle'" class="flex flex-col gap-3" role="status" aria-busy="true">
      <div class="skeleton h-8 w-2/3" />
      <div class="skeleton h-40 w-full" />
    </div>
    <p v-else-if="state === 'not-found'" role="alert" class="alert alert-warning alert-soft">{{ locale.notFound }}</p>
    <div v-else-if="state === 'error'" role="alert" class="alert alert-error alert-soft flex flex-wrap justify-between gap-3">
      <span>{{ locale.loadError }}</span>
      <button type="button" class="btn btn-sm" @click="detail.load">{{ locale.retry }}</button>
    </div>
    <template v-else-if="proposal">
      <header class="flex flex-col gap-2">
        <span class="text-xs text-(--ph-ink-3)">{{ locale.proposalNumber.replace('{n}', String(proposal.number)) }}<template v-if="proposal.track"> · {{ proposal.track }}</template></span>
        <h1 class="text-3xl leading-tight font-semibold">{{ proposal.title }}</h1>
        <span class="badge badge-soft badge-info">{{ proposal.decisionLabel ?? proposal.statusLabel }}</span>
      </header>

      <p v-if="notice" role="status" class="alert alert-success alert-soft text-sm">{{ notice }}</p>
      <p v-if="error" role="alert" class="alert alert-error alert-soft text-sm">{{ error }}</p>

      <section v-if="proposal.confirmation && proposal.isOwner" class="card border border-(--ph-brand) bg-(--ph-surface) p-6" data-testid="confirmation">
        <template v-if="proposal.confirmation.status === 'pending'">
          <h2 class="text-xl font-semibold">{{ locale.confirmationTitle }}</h2>
          <p class="mt-1 text-(--ph-ink-2)">{{ locale.confirmationText.replace('{data}', longDate(proposal.confirmation.deadlineAt)) }}</p>
          <div class="mt-4 flex flex-wrap gap-3">
            <button type="button" class="btn btn-primary" :disabled="!!busy" @click="run(detail.confirm, locale.confirmed)">{{ locale.confirm }}</button>
            <button type="button" class="btn btn-ghost" :disabled="!!busy" @click="dialog = 'decline'">{{ locale.decline }}</button>
          </div>
        </template>
        <p v-else>{{ locale.confirmationStatus.replace('{status}', proposal.confirmation.statusLabel) }}</p>
      </section>

      <section class="card border border-(--ph-line) bg-(--ph-surface) p-6">
        <p class="text-sm text-(--ph-ink-2)">{{ [proposal.formatLabel, proposal.levelLabel].filter(Boolean).join(' · ') }}</p>
        <h2 class="mt-3 text-sm font-semibold">{{ locale.summaryLabel }}</h2>
        <p class="whitespace-pre-line">{{ proposal.summary }}</p>
        <h2 class="mt-3 text-sm font-semibold">{{ locale.audienceLabel }}</h2>
        <p class="whitespace-pre-line">{{ proposal.audience }}</p>
      </section>

      <div v-if="cfp?.status === 'open' && proposal.status !== 'withdrawn' && !proposal.decision" class="flex flex-wrap gap-3">
        <NuxtLink :to="`/cfp?proposta=${proposal.id}`" class="btn btn-primary">{{ proposal.status === 'draft' ? locale.continueDraft : locale.editSubmitted }}</NuxtLink>
        <button v-if="proposal.isOwner && proposal.status === 'submitted'" type="button" class="btn btn-ghost" @click="dialog = 'withdraw'">{{ locale.withdraw }}</button>
      </div>
    </template>

    <dialog class="modal" :open="dialog !== null" :aria-labelledby="dialog ? 'proposal-dialog-title' : undefined">
      <div class="modal-box flex flex-col gap-4">
        <h2 id="proposal-dialog-title" class="text-lg font-semibold">{{ dialog === 'withdraw' ? locale.withdrawTitle : locale.declineTitle }}</h2>
        <p class="text-sm">{{ dialog === 'withdraw' ? locale.withdrawText : locale.declineText }}</p>
        <TextAreaField v-if="dialog === 'decline'" v-model="reason" :label="locale.declineReason" :maxlength="500" :rows="3" />
        <div class="modal-action">
          <button type="button" class="btn btn-ghost" @click="dialog = null">{{ locale.cancel }}</button>
          <button v-if="dialog === 'withdraw'" type="button" class="btn btn-error" :disabled="!!busy" @click="run(detail.withdraw, locale.withdrawn)">{{ locale.withdraw }}</button>
          <button v-else type="button" class="btn btn-error" :disabled="!!busy" @click="run(() => detail.decline(reason), locale.declined)">{{ locale.decline }}</button>
        </div>
      </div>
    </dialog>
  </section>
</template>
