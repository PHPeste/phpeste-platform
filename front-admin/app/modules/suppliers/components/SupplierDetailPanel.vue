<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from '@/application/shared/composables/useToast'
import InfoCard from './InfoCard.vue'
import ContactsCard from './ContactsCard.vue'
import AttachmentsCard from './AttachmentsCard.vue'
import HireModal from './HireModal.vue'
import DeclineModal from './DeclineModal.vue'
import QuoteModal from './QuoteModal.vue'
import ActivityModal from './ActivityModal.vue'
import { QUOTE_TONE, STATUS_TONE } from './statusTone'
import { useSupplier } from '../composables/useSupplier'
import { formatCents, formatDateTime, formatDay, initials } from '../composables/format'
import locale from '../locales/pt-br'
import type { MutationResult } from '../types'

const props = defineProps<{ editionId: string, linkId: string, readOnly: boolean }>()

const detail = useSupplier(props.editionId, props.linkId)
const { supplier, members, state } = detail
const toast = useToast()
const router = useRouter()

const hireOpen = ref(false)
const declineOpen = ref(false)
const quoteOpen = ref(false)
const activityOpen = ref(false)
const removeOpen = ref(false)
const busy = ref<string | null>(null)

async function run(key: string, action: () => Promise<MutationResult>, success: string) {
  busy.value = key
  const result = await action()
  busy.value = null
  if (result.ok) toast.success(success)
  else toast.error(result.message)
  return result.ok
}

async function removeSupplier() {
  if (await run('remove', detail.remove, locale.removed)) {
    removeOpen.value = false
    router.push('/fornecedores')
  }
}

const named = (text: string) => text.replace('{nome}', supplier.value?.name ?? '')

onMounted(() => detail.load())
</script>

<template>
  <section class="flex flex-col gap-6">
    <NuxtLink to="/fornecedores" class="w-fit text-sm">← {{ locale.backToList }}</NuxtLink>

    <PhPageSkeleton v-if="state === 'loading'" :cards="3" :lines="5" />
    <PhLoadError v-else-if="state === 'error'" :title="locale.detailError" @retry="detail.load" />
    <PhEmptyState v-else-if="state === 'missing'" :title="locale.missing">
      <PhButton variant="secondary" to="/fornecedores">{{ locale.backToList }}</PhButton>
    </PhEmptyState>

    <template v-else-if="supplier">
      <header class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <span class="grid size-14 place-items-center rounded-full bg-(--ph-surface-2) text-lg font-semibold" aria-hidden="true">{{ initials(supplier.name) }}</span>
          <div class="flex flex-col gap-1">
            <h1 class="text-3xl leading-tight font-semibold">{{ supplier.name }}</h1>
            <p class="flex flex-wrap items-center gap-2 text-(--ph-ink-2)">
              <span>{{ supplier.serviceLabel }}</span>
              <PhBadge :status="STATUS_TONE[supplier.status]" data-testid="supplier-status">{{ supplier.statusLabel }}</PhBadge>
              <span v-if="supplier.status === 'hired' && supplier.contractedCents !== null" class="text-sm" data-testid="supplier-contracted">{{ locale.contractedValue.replace('{valor}', formatCents(supplier.contractedCents)) }}</span>
            </p>
            <p v-if="supplier.status === 'declined' && supplier.declineReason" class="text-sm text-(--ph-ink-3)">{{ locale.declinedBecause.replace('{motivo}', supplier.declineReason) }}</p>
          </div>
        </div>
        <div v-if="!readOnly" class="flex flex-wrap gap-2">
          <PhButton v-if="supplier.status !== 'hired'" variant="ghost" class="text-error" @click="removeOpen = true">{{ locale.removeSupplier }}</PhButton>
          <PhButton v-if="supplier.status === 'hired' || supplier.status === 'declined'" variant="secondary" :loading="busy === 'reopen'" @click="run('reopen', detail.reopen, locale.reopened)">{{ locale.reopen }}</PhButton>
          <PhButton v-if="supplier.status !== 'declined'" variant="secondary" @click="declineOpen = true">{{ locale.decline }}</PhButton>
          <PhButton v-if="supplier.status !== 'hired'" @click="hireOpen = true">{{ locale.hire }}</PhButton>
        </div>
      </header>

      <p v-if="readOnly" role="status" class="alert alert-info alert-soft text-sm">{{ locale.readOnly }}</p>

      <div class="grid gap-6 xl:grid-cols-[3fr_2fr]">
        <div class="flex flex-col gap-6">
          <section class="card overflow-hidden border border-base-300 bg-base-100" aria-labelledby="quotes-title">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-(--ph-line) px-5 py-4">
              <h2 id="quotes-title" class="text-lg font-semibold">{{ locale.quotes }}</h2>
              <PhButton v-if="!readOnly" variant="secondary" @click="quoteOpen = true">{{ locale.newQuote }}</PhButton>
            </div>
            <div v-if="!supplier.quotes.length" class="p-5">
              <PhEmptyState :title="locale.noQuotes" :description="locale.noQuotesHint">
                <PhButton v-if="!readOnly" variant="secondary" @click="quoteOpen = true">{{ locale.newQuote }}</PhButton>
              </PhEmptyState>
            </div>
            <PhTable v-else :caption="locale.quotes">
              <thead>
                <tr>
                  <th scope="col">{{ locale.colProposal }}</th>
                  <th scope="col" class="text-right">{{ locale.colValue }}</th>
                  <th scope="col">{{ locale.colValidity }}</th>
                  <th scope="col">{{ locale.colStatus }}</th>
                  <th v-if="!readOnly" scope="col"><span class="sr-only">{{ locale.remove }}</span></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="quote in supplier.quotes" :key="quote.id">
                  <td>
                    <span class="block font-medium">{{ quote.description }}</span>
                    <span class="block text-xs text-(--ph-ink-3)">{{ formatDay(quote.receivedOn) }}<template v-if="quote.recordedBy"> · {{ locale.by.replace('{nome}', quote.recordedBy) }}</template></span>
                  </td>
                  <td class="text-right tabular-nums">{{ formatCents(quote.amountCents) }}</td>
                  <td class="text-sm">
                    {{ quote.validUntil ? locale.validUntil.replace('{data}', formatDay(quote.validUntil)) : locale.noValidity }}
                    <PhBadge v-if="quote.expired && quote.status === 'received'" status="warn" class="ml-1">{{ locale.expired }}</PhBadge>
                  </td>
                  <td><PhBadge :status="QUOTE_TONE[quote.status]">{{ quote.statusLabel }}</PhBadge></td>
                  <td v-if="!readOnly" class="text-right whitespace-nowrap">
                    <template v-if="quote.status !== 'accepted'">
                      <button
                        v-if="quote.status === 'received'"
                        type="button"
                        class="btn btn-ghost btn-sm"
                        :aria-label="locale.rejectQuoteLabel.replace('{proposta}', quote.description)"
                        @click="run(`reject-${quote.id}`, () => detail.rejectQuote(quote.id), locale.quoteRejected)"
                      >
                        {{ locale.rejectQuote }}
                      </button>
                      <button
                        type="button"
                        class="btn btn-ghost btn-sm text-error"
                        :aria-label="locale.removeQuoteLabel.replace('{proposta}', quote.description)"
                        @click="run(`delete-${quote.id}`, () => detail.deleteQuote(quote.id), locale.quoteRemoved)"
                      >
                        {{ locale.remove }}
                      </button>
                    </template>
                  </td>
                </tr>
              </tbody>
            </PhTable>
          </section>

          <InfoCard :supplier="supplier" :members="members" :disabled="readOnly" :save="detail.save" @saved="toast.success(locale.saved)" />
        </div>

        <div class="flex flex-col gap-6">
          <ContactsCard :supplier="supplier" :disabled="readOnly" :add="detail.addContact" :remove="detail.deleteContact" />
          <AttachmentsCard :attachments="supplier.attachments" :disabled="readOnly" :upload="detail.upload" :remove="detail.deleteAttachment" :url="detail.attachmentUrl" />

          <section class="card border border-base-300 bg-base-100 p-5" aria-labelledby="activities-title">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
              <h2 id="activities-title" class="text-lg font-semibold">{{ locale.activities }}</h2>
              <PhButton v-if="!readOnly" variant="secondary" @click="activityOpen = true">{{ locale.logActivity }}</PhButton>
            </div>
            <ol v-if="supplier.activities.length" class="ml-1.5 flex flex-col gap-3 border-l border-(--ph-line) pl-4" data-testid="supplier-activities">
              <li v-for="activity in supplier.activities" :key="activity.id" class="relative">
                <span class="absolute top-1.5 -left-[1.3rem] size-2 rounded-full" :class="activity.kind === 'note' ? 'bg-(--ph-brand)' : 'bg-(--ph-ink-3)'" aria-hidden="true" />
                <p class="font-medium">{{ activity.summary }}</p>
                <p class="text-xs text-(--ph-ink-3)">
                  <template v-if="activity.channelLabel">{{ activity.channelLabel }} · </template>{{ formatDateTime(activity.at) }}<template v-if="activity.authorName"> · {{ activity.authorName }}</template>
                </p>
              </li>
            </ol>
            <p v-else class="text-sm text-(--ph-ink-3)">{{ locale.noActivities }}</p>
          </section>
        </div>
      </div>

      <HireModal v-model="hireOpen" :supplier="supplier" :hire="detail.hire" @saved="toast.success(named(locale.hired))" />
      <DeclineModal v-model="declineOpen" :name="supplier.name" :decline="detail.decline" @saved="toast.success(named(locale.declined))" />
      <QuoteModal v-model="quoteOpen" :record="detail.recordQuote" @saved="toast.success(locale.quoteSaved)" />
      <ActivityModal v-model="activityOpen" :log="detail.logActivity" @saved="toast.success(locale.activitySaved)" />
      <PhModal v-model="removeOpen" :title="named(locale.removeTitle)">
        <p>{{ locale.removeText }}</p>
        <template #footer>
          <PhButton variant="ghost" @click="removeOpen = false">{{ locale.back }}</PhButton>
          <PhButton variant="danger" :loading="busy === 'remove'" @click="removeSupplier">{{ locale.removeSupplier }}</PhButton>
        </template>
      </PhModal>
    </template>
  </section>
</template>
