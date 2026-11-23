<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import ContactCard from './ContactCard.vue'
import AttachmentsCard from './AttachmentsCard.vue'
import StageModal from './StageModal.vue'
import PaymentModal from './PaymentModal.vue'
import ContactModal from './ContactModal.vue'
import DeliverableModal from './DeliverableModal.vue'
import { DELIVERABLE_TONE, STAGE_TONE } from './statusTone'
import { useSponsor } from '../composables/useSponsor'
import { formatCents, formatDateTime, formatDay, initials } from '../composables/format'
import locale from '../locales/pt-br'
import type { Deliverable, DeliverableStatus, MutationResult } from '../types'

const props = defineProps<{ editionId: string, sponsorId: string, readOnly: boolean }>()

const detail = useSponsor(props.editionId, props.sponsorId)
const { sponsor, tiers, members, deal, selectedDealId, state } = detail
const toast = useToast()

const stageOpen = ref(false)
const paymentOpen = ref(false)
const contactOpen = ref(false)
const deliverableOpen = ref(false)
const busy = ref<string | null>(null)

const deals = computed(() => sponsor.value?.deals ?? [])
const dealOptions = computed(() => deals.value.map((item) => ({ value: item.id, label: `${item.tierName ?? locale.noTier} · ${locale.stages[item.stage]}` })))
const dealPick = computed({ get: () => deal.value?.id ?? '', set: (value: string) => { selectedDealId.value = value } })
const statuses = Object.entries(locale.deliverableStatuses).map(([value, label]) => ({ value, label }))

async function run(key: string, action: () => Promise<MutationResult>, success: string) {
  busy.value = key
  const result = await action()
  busy.value = null
  if (result.ok) toast.success(success)
  else toast.error(result.message)
}

async function openDeal() {
  await run('open-deal', () => detail.openDeal({ tierId: tiers.value[0]?.id ?? '', valueCents: tiers.value[0] ? null : 0, ownerId: '' }), locale.saved)
}

function setStatus(item: Deliverable, event: Event) {
  const status = (event.target as HTMLSelectElement).value as DeliverableStatus
  run(`status-${item.id}`, () => detail.setDeliverableStatus(item.id, status), locale.deliverableSaved)
}

onMounted(() => detail.load())
</script>

<template>
  <section class="flex flex-col gap-6">
    <NuxtLink to="/patrocinadores" class="w-fit text-sm">← {{ locale.backToList }}</NuxtLink>

    <PhPageSkeleton v-if="state === 'loading'" :cards="3" :lines="5" />
    <PhLoadError v-else-if="state === 'error'" :title="locale.detailError" @retry="detail.load" />
    <PhEmptyState v-else-if="state === 'missing'" :title="locale.missing">
      <PhButton variant="secondary" to="/patrocinadores">{{ locale.backToList }}</PhButton>
    </PhEmptyState>

    <template v-else-if="sponsor">
      <header class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <span class="grid size-14 place-items-center rounded-full bg-(--ph-surface-2) text-lg font-semibold" aria-hidden="true">{{ initials(sponsor.name) }}</span>
          <div class="flex flex-col gap-1">
            <h1 class="text-3xl leading-tight font-semibold">{{ sponsor.name }}</h1>
            <p v-if="deal" class="flex flex-wrap items-center gap-2 text-(--ph-ink-2)">
              <span>{{ deal.tierName ?? locale.noTier }} · {{ formatCents(deal.valueCents) }}</span>
              <PhBadge :status="STAGE_TONE[deal.stage]" data-testid="sponsor-stage">{{ deal.stageLabel }}</PhBadge>
            </p>
            <p v-else class="text-(--ph-ink-2)">{{ locale.noDeal }}</p>
          </div>
        </div>
        <div v-if="!readOnly" class="flex flex-wrap gap-2">
          <template v-if="deal">
            <PhButton variant="secondary" @click="stageOpen = true">{{ locale.changeStage }}</PhButton>
            <PhButton v-if="deal.stage !== 'lost' && deal.outstandingCents > 0" @click="paymentOpen = true">{{ locale.recordPayment }}</PhButton>
          </template>
          <PhButton v-else :loading="busy === 'open-deal'" @click="openDeal">{{ locale.openDeal }}</PhButton>
        </div>
      </header>

      <p v-if="readOnly" role="status" class="alert alert-info alert-soft text-sm">{{ locale.readOnly }}</p>
      <div v-if="deals.length > 1" class="max-w-sm">
        <PhSelect id="deal-pick" v-model="dealPick" :label="locale.dealPick" :options="dealOptions" />
      </div>

      <div class="grid gap-6 xl:grid-cols-[3fr_2fr]">
        <div class="flex flex-col gap-6">
          <ContactCard :sponsor="sponsor" :deal="deal" :tiers="tiers" :members="members" :disabled="readOnly" :save-sponsor="detail.saveSponsor" :save-deal="detail.saveDeal" @saved="toast.success(locale.saved)" />

          <section v-if="deal" class="card border border-base-300 bg-base-100 p-5" aria-labelledby="payments-title">
            <h2 id="payments-title" class="mb-3 text-lg font-semibold">{{ locale.payments }}</h2>
            <dl class="mb-3 grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 tabular-nums">
              <dt class="text-(--ph-ink-2)">{{ locale.received }}</dt><dd class="text-right font-medium" data-testid="sponsor-received">{{ formatCents(deal.receivedCents) }}</dd>
              <dt class="text-(--ph-ink-2)">{{ locale.outstanding }}</dt><dd class="text-right">{{ formatCents(deal.outstandingCents) }}</dd>
            </dl>
            <ul v-if="deal.payments.length" class="flex flex-col divide-y divide-(--ph-line) rounded-(--radius-box) border border-(--ph-line)">
              <li v-for="payment in deal.payments" :key="payment.id" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                <div>
                  <p class="font-medium tabular-nums">{{ formatCents(payment.amountCents) }}</p>
                  <p class="text-xs text-(--ph-ink-3)">{{ formatDay(payment.paidOn) }}<template v-if="payment.note"> · {{ payment.note }}</template><template v-if="payment.recordedBy"> · {{ locale.by.replace('{nome}', payment.recordedBy) }}</template></p>
                </div>
                <button
                  v-if="!readOnly"
                  type="button"
                  class="btn btn-ghost btn-sm text-error"
                  :aria-label="locale.removePayment.replace('{valor}', formatCents(payment.amountCents))"
                  @click="run(`payment-${payment.id}`, () => detail.deletePayment(payment.id), locale.paymentRemoved)"
                >
                  {{ locale.remove }}
                </button>
              </li>
            </ul>
            <p v-else class="text-sm text-(--ph-ink-3)">{{ locale.noPayments }}</p>
          </section>

          <section v-if="deal" class="card overflow-hidden border border-base-300 bg-base-100" aria-labelledby="deliverables-title">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-(--ph-line) px-5 py-4">
              <h2 id="deliverables-title" class="text-lg font-semibold">{{ locale.deliverables }}</h2>
              <PhButton v-if="!readOnly" variant="secondary" @click="deliverableOpen = true">{{ locale.newDeliverable }}</PhButton>
            </div>
            <div v-if="!deal.deliverables.length" class="p-5">
              <PhEmptyState :title="locale.noDeliverables" :description="locale.noDeliverablesHint">
                <PhButton v-if="!readOnly" variant="secondary" @click="deliverableOpen = true">{{ locale.newDeliverable }}</PhButton>
              </PhEmptyState>
            </div>
            <PhTable v-else :caption="locale.deliverables">
              <thead>
                <tr>
                  <th scope="col">{{ locale.colDeliverable }}</th>
                  <th scope="col">{{ locale.colDue }}</th>
                  <th scope="col">{{ locale.colOwner }}</th>
                  <th scope="col">{{ locale.colStatus }}</th>
                  <th v-if="!readOnly" scope="col"><span class="sr-only">{{ locale.remove }}</span></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in deal.deliverables" :key="item.id">
                  <td class="font-medium">{{ item.title }}</td>
                  <td class="text-sm tabular-nums">
                    {{ item.dueOn ? formatDay(item.dueOn) : locale.noDue }}
                    <PhBadge v-if="item.overdue" status="danger" class="ml-1">{{ locale.late }}</PhBadge>
                  </td>
                  <td class="text-sm">{{ item.ownerName ?? '—' }}</td>
                  <td>
                    <select
                      v-if="!readOnly"
                      class="select select-sm"
                      :value="item.status"
                      :aria-label="locale.statusLabel.replace('{entrega}', item.title)"
                      :disabled="busy === `status-${item.id}`"
                      @change="setStatus(item, $event)"
                    >
                      <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                    <PhBadge v-else :status="DELIVERABLE_TONE[item.status]">{{ item.statusLabel }}</PhBadge>
                  </td>
                  <td v-if="!readOnly" class="text-right">
                    <button type="button" class="btn btn-ghost btn-sm text-error" :aria-label="locale.removeDeliverable.replace('{entrega}', item.title)" @click="run(`del-${item.id}`, () => detail.deleteDeliverable(item.id), locale.deliverableRemoved)">
                      {{ locale.remove }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </PhTable>
          </section>
        </div>

        <div class="flex flex-col gap-6">
          <AttachmentsCard :attachments="sponsor.attachments" :disabled="readOnly" :upload="detail.upload" :remove="detail.deleteAttachment" :url="detail.attachmentUrl" />

          <section class="card border border-base-300 bg-base-100 p-5" aria-labelledby="history-title">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
              <h2 id="history-title" class="text-lg font-semibold">{{ locale.history }}</h2>
              <PhButton v-if="!readOnly" variant="secondary" @click="contactOpen = true">{{ locale.logContact }}</PhButton>
            </div>
            <ol v-if="sponsor.contacts.length" class="flex flex-col gap-3" data-testid="sponsor-history">
              <li v-for="contact in sponsor.contacts" :key="contact.id" class="flex items-start gap-3">
                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-(--ph-surface-2) text-xs font-semibold" aria-hidden="true">{{ initials(contact.authorName ?? '?') }}</span>
                <div class="min-w-0 flex-1">
                  <p class="font-medium">{{ contact.summary }}</p>
                  <p class="text-xs text-(--ph-ink-3)">{{ contact.channelLabel }} · {{ formatDateTime(contact.at) }}<template v-if="contact.authorName"> · {{ contact.authorName }}</template></p>
                </div>
                <button v-if="!readOnly" type="button" class="btn btn-ghost btn-xs" :aria-label="locale.removeContact" @click="run(`contact-${contact.id}`, () => detail.deleteContact(contact.id), locale.contactRemoved)">✕</button>
              </li>
            </ol>
            <p v-else class="text-sm text-(--ph-ink-3)">{{ locale.noContacts }}</p>
          </section>
        </div>
      </div>

      <template v-if="deal">
        <StageModal v-model="stageOpen" :deal="deal" :company="sponsor.name" :move="detail.move" @saved="toast.success(locale.stageChanged)" />
        <PaymentModal v-model="paymentOpen" :deal="deal" :record="detail.recordPayment" @saved="toast.success(locale.paymentSaved)" />
        <DeliverableModal v-model="deliverableOpen" :members="members" :save="detail.createDeliverable" @saved="toast.success(locale.deliverableSaved)" />
      </template>
      <ContactModal v-model="contactOpen" :log="detail.logContact" @saved="toast.success(locale.contactSaved)" />
    </template>
  </section>
</template>
