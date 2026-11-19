<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import AccountForm from './AccountForm.vue'
import ReceiverPreview from './ReceiverPreview.vue'
import SettingsModal from './SettingsModal.vue'
import WebhookEvents from './WebhookEvents.vue'
import { usePayments } from '../composables/usePayments'
import { formatDateTime } from '../composables/format'
import locale from '../locales/pt-br'
import type { WebhookEvent } from '../types'

const props = defineProps<{ editionId: string, readOnly: boolean }>()

const payments = usePayments(props.editionId)
const { active, previous, drivers, state, events, eventsPage, eventsPageCount, eventsStatus, eventsState } = payments
const toast = useToast()

const formOpen = ref(false)
const settingsOpen = ref(false)
const verifying = ref(false)
const verifyError = ref<string | null>(null)

const steps = computed(() => {
  const account = active.value
  if (!account) return []
  return [
    { key: 'keys', title: locale.stepKeys, done: true, detail: locale.stepKeysDone.replace('{hint}', account.credentialsHint) },
    { key: 'verify', title: locale.stepVerify, done: account.verifiedAt !== null, detail: account.verifiedAt ? locale.stepVerifyDone.replace('{data}', formatDateTime(account.verifiedAt)) : locale.stepVerifyPending },
    { key: 'webhook', title: locale.stepWebhook, done: account.webhookVerifiedAt !== null, detail: account.webhookVerifiedAt ? locale.stepWebhookDone.replace('{data}', formatDateTime(account.webhookVerifiedAt)) : locale.stepWebhookPending },
  ]
})

async function verify() {
  verifying.value = true
  verifyError.value = null
  const result = await payments.verify()
  verifying.value = false
  if (result.ok) toast.success(locale.testOk)
  else verifyError.value = result.message
}

async function copyWebhook() {
  try {
    await navigator.clipboard.writeText(active.value?.webhookUrl ?? '')
    toast.success(locale.copied)
  } catch {
    toast.error(locale.copyFailed)
  }
}

async function refresh() {
  await Promise.all([payments.load(), payments.loadEvents()])
}

async function reprocess(event: WebhookEvent) {
  const updated = await payments.reprocess(event)
  if (updated) toast.success(locale.reprocessed.replace('{status}', updated.statusLabel.toLowerCase()))
  else toast.error(locale.genericError)
}

function saved() {
  toast.success(locale.saved)
  verifyError.value = null
  payments.loadEvents(1)
}

onMounted(() => Promise.all([payments.load(), payments.loadEvents(1, '')]))
</script>

<template>
  <section class="flex flex-col gap-6">
    <header class="flex flex-wrap items-end justify-between gap-3">
      <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl leading-tight font-semibold">{{ locale.title }}</h1>
        <p class="text-(--ph-ink-2)">{{ locale.subtitle }}</p>
      </div>
      <PhButton v-if="active && !readOnly" variant="secondary" @click="formOpen = true">{{ locale.replace }}</PhButton>
    </header>

    <p v-if="readOnly" role="status" class="alert alert-info alert-soft text-sm">{{ locale.readOnly }}</p>

    <PhPageSkeleton v-if="state === 'loading'" :cards="2" :lines="5" />
    <PhLoadError v-else-if="state === 'error'" :title="locale.loadError" @retry="payments.load" />
    <PhEmptyState v-else-if="!active" :title="locale.emptyTitle" :description="locale.emptyHint">
      <PhButton v-if="!readOnly" @click="formOpen = true">{{ locale.connect }}</PhButton>
    </PhEmptyState>

    <template v-else>
      <div class="grid gap-6 lg:grid-cols-2">
        <PhCard :title="locale.readiness">
          <div class="flex flex-col gap-4" data-testid="readiness">
            <div class="flex flex-wrap items-center gap-2">
              <PhBadge :status="active.ready ? 'ok' : 'warn'">{{ active.ready ? locale.readyBadge : locale.notReadyBadge }}</PhBadge>
              <span class="text-sm text-(--ph-ink-2)">{{ active.ready ? locale.readyHint : locale.notReadyHint }}</span>
            </div>
            <ol class="flex flex-col gap-3">
              <li v-for="(step, index) in steps" :key="step.key" class="flex gap-3" :data-testid="`step-${step.key}`">
                <span
                  class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                  :class="step.done ? 'bg-(--ph-ok-bg) text-(--ph-ok)' : 'bg-(--ph-surface-2) text-(--ph-ink-3)'"
                  aria-hidden="true"
                >{{ step.done ? '✓' : index + 1 }}</span>
                <div class="flex flex-col">
                  <span class="font-medium">{{ step.title }}<span class="sr-only">{{ step.done ? ' (feito)' : ' (pendente)' }}</span></span>
                  <span class="text-sm text-(--ph-ink-2)">{{ step.detail }}</span>
                </div>
              </li>
            </ol>
            <div v-if="!readOnly" class="flex flex-col gap-2">
              <div>
                <PhButton :variant="active.verifiedAt ? 'secondary' : 'primary'" :loading="verifying" @click="verify">{{ locale.testConnection }}</PhButton>
              </div>
              <p v-if="verifyError" role="alert" class="alert alert-error alert-soft text-sm">{{ verifyError }}</p>
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium" for="payments-webhook-url">{{ locale.webhookUrl }}</label>
              <div class="flex gap-2">
                <input id="payments-webhook-url" class="input w-full font-mono text-xs" :value="active.webhookUrl ?? ''" readonly @focus="($event.target as HTMLInputElement).select()">
                <PhButton variant="secondary" @click="copyWebhook">{{ locale.copy }}</PhButton>
              </div>
              <p class="text-sm text-(--ph-ink-2)" data-testid="last-event">
                {{ active.lastEventAt ? locale.lastEvent.replace('{data}', formatDateTime(active.lastEventAt)) : locale.noEventYet }}
                <button type="button" class="link ml-1 text-sm" @click="refresh">{{ locale.refresh }}</button>
              </p>
              <p v-if="active.webhookRejections > 0" class="text-sm text-(--ph-warn)">
                {{ locale.rejections.replace('{n}', String(active.webhookRejections)).replace('{data}', formatDateTime(active.webhookRejectedAt)) }}
              </p>
            </div>
          </div>
        </PhCard>

        <PhCard :title="locale.account">
          <div class="flex flex-col gap-4" data-testid="account">
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
              <dt class="text-(--ph-ink-3)">{{ locale.driver }}</dt>
              <dd>{{ active.driverLabel }}</dd>
              <dt class="text-(--ph-ink-3)">{{ locale.environment }}</dt>
              <dd><PhBadge :status="active.environment === 'production' ? 'ok' : 'warn'">{{ active.environmentLabel }}</PhBadge></dd>
              <dt class="text-(--ph-ink-3)">{{ locale.apiKeyLabel }}</dt>
              <dd>
                <span class="font-mono">{{ active.credentialsHint }}</span>
                <span class="block text-xs text-(--ph-ink-3)">{{ locale.keyOnlyReplace }}</span>
              </dd>
              <dt class="text-(--ph-ink-3)">{{ locale.holder }}</dt>
              <dd>{{ active.holderName }}<span class="block text-xs text-(--ph-ink-3)">{{ active.holderTypeLabel }}</span></dd>
              <dt class="text-(--ph-ink-3)">{{ locale.document }}</dt>
              <dd class="font-mono">{{ active.holderDocument }}</dd>
              <dt class="text-(--ph-ink-3)">{{ locale.methods }}</dt>
              <dd>{{ active.methodLabels.join(', ') }}</dd>
              <template v-if="active.methods.includes('card')">
                <dt class="text-(--ph-ink-3)">{{ locale.installments }}</dt>
                <dd>{{ active.maxInstallments > 1 ? locale.installmentsValue.replace('{n}', String(active.maxInstallments)) : locale.noInstallments }}</dd>
              </template>
              <template v-if="active.methods.includes('pix')">
                <dt class="text-(--ph-ink-3)">{{ locale.pixExpiration }}</dt>
                <dd>{{ locale.minutes.replace('{n}', String(active.pixExpirationMinutes)) }}</dd>
              </template>
            </dl>
            <div v-if="!readOnly">
              <PhButton variant="secondary" @click="settingsOpen = true">{{ locale.edit }}</PhButton>
            </div>
            <ReceiverPreview :holder-name="active.holderName" :holder-type="active.holderType" :methods="active.methods" :max-installments="active.maxInstallments" :sandbox="active.environment === 'sandbox'" />
          </div>
        </PhCard>
      </div>

      <WebhookEvents
        :events="events"
        :state="eventsState"
        :status="eventsStatus"
        :page="eventsPage"
        :page-count="eventsPageCount"
        :disabled="readOnly"
        @filter="payments.loadEvents(1, $event)"
        @page="payments.loadEvents($event)"
        @reprocess="reprocess"
        @retry="payments.loadEvents()"
      />

      <PhCard v-if="previous.length" :title="locale.previous">
        <div class="flex flex-col gap-2" data-testid="previous-accounts">
          <p class="text-sm text-(--ph-ink-2)">{{ locale.previousHint }}</p>
          <ul class="flex flex-col divide-y divide-(--ph-line)">
            <li v-for="account in previous" :key="account.id" class="flex flex-wrap items-baseline justify-between gap-2 py-2 text-sm">
              <span><span class="font-medium">{{ account.driverLabel }}</span> · {{ account.holderName }} · <span class="font-mono">{{ account.credentialsHint }}</span></span>
              <span class="text-(--ph-ink-3)">{{ locale.retiredAt.replace('{data}', formatDateTime(account.retiredAt)) }}</span>
            </li>
          </ul>
        </div>
      </PhCard>
    </template>

    <AccountForm v-model="formOpen" :drivers="drivers" :current="active" :create="payments.create" @saved="saved" />
    <SettingsModal v-if="active" v-model="settingsOpen" :account="active" :update="payments.update" @saved="toast.success(locale.settingsSaved)" />
  </section>
</template>
