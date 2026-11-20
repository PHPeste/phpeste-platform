<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import { formatDateTime } from '../composables/format'
import locale from '../locales/pt-br'
import type { AttendeeDetail, AttendeeInput, MutationResult } from '../types'

const props = defineProps<{
  detail: AttendeeDetail | null
  state: 'idle' | 'loading' | 'ready' | 'error'
  disabled: boolean
  canSeePrivate: boolean
  save: (input: AttendeeInput) => Promise<MutationResult>
  resend: () => Promise<MutationResult>
}>()
const open = defineModel<boolean>({ default: false })
defineEmits<{ retry: [] }>()

const SIZES = ['PP', 'P', 'M', 'G', 'GG', 'XGG', 'BL-P', 'BL-M', 'BL-G', 'BL-GG']
const sizeOptions = [{ value: '', label: locale.tshirtNone }, ...SIZES.map((size) => ({ value: size, label: size }))]

const toast = useToast()
const form = reactive<AttendeeInput>({ name: '', badgeName: '', company: '', tshirtSize: '', accessibility: '' })
const errors = ref<Record<string, string>>({})
const busy = ref<'save' | 'resend' | null>(null)

const attendee = computed(() => props.detail?.attendee ?? null)
const editable = computed(() => !props.disabled && attendee.value?.active === true)
const labels = { name: locale.name, badgeName: locale.badgeName, company: locale.company, tshirtSize: locale.tshirt, accessibility: locale.accessibility }
const fieldIds = { name: 'attendee-name', badgeName: 'attendee-badge', company: 'attendee-company', tshirtSize: 'attendee-tshirt', accessibility: 'attendee-accessibility' }

watch(() => attendee.value?.id, () => {
  const a = attendee.value
  if (!a) return
  form.name = a.name
  form.badgeName = a.badgeName ?? ''
  form.company = a.company ?? ''
  form.tshirtSize = a.tshirtSize ?? ''
  form.accessibility = a.accessibility ?? ''
  errors.value = {}
}, { immediate: true })

async function submit() {
  busy.value = 'save'
  const result = await props.save({ ...form })
  busy.value = null
  if (result.ok) {
    errors.value = {}
    toast.success(locale.saved)
    return
  }
  errors.value = result.fieldErrors
  if (!Object.keys(result.fieldErrors).length) toast.error(result.message)
}

async function resendTicket() {
  busy.value = 'resend'
  const result = await props.resend()
  busy.value = null
  if (result.ok) toast.success(locale.resent.replace('{email}', attendee.value?.email ?? ''))
  else toast.error(result.message)
}

const show = (value: string | null) => value ?? locale.empty_value
</script>

<template>
  <PhDrawer v-model="open" :title="attendee ? attendee.name : locale.title">
    <PhPageSkeleton v-if="state === 'loading' && !detail" :cards="1" :lines="6" />
    <PhLoadError v-else-if="state === 'error'" :title="locale.detailError" @retry="$emit('retry')" />

    <div v-else-if="detail && attendee" class="flex flex-col gap-6" data-testid="attendee-detail">
      <section class="flex flex-col gap-1">
        <h3 class="text-xs font-semibold tracking-wide text-(--ph-ink-3) uppercase">{{ locale.ticket }}</h3>
        <div class="flex flex-wrap items-center gap-2">
          <span class="font-medium">{{ attendee.ticketTypeName }}</span>
          <PhBadge :status="attendee.active ? 'ok' : 'neutral'">{{ attendee.statusLabel }}</PhBadge>
        </div>
        <p class="text-(--ph-ink-2)">{{ attendee.email }}</p>
        <p class="text-xs text-(--ph-ink-3) tabular-nums">
          {{ locale.code.replace('{codigo}', attendee.code) }}<template v-if="attendee.orderNumber"> · {{ locale.order.replace('{n}', String(attendee.orderNumber)) }}</template>
        </p>
        <p v-if="attendee.lastSentAt" class="text-xs text-(--ph-ink-3)">{{ locale.lastSent.replace('{data}', formatDateTime(attendee.lastSentAt)) }}</p>
      </section>

      <form id="attendee-form" class="flex flex-col gap-4" novalidate :aria-label="locale.editTitle" @submit.prevent="submit">
        <h3 class="text-xs font-semibold tracking-wide text-(--ph-ink-3) uppercase">{{ locale.editTitle }}</h3>
        <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
        <PhInput id="attendee-name" v-model="form.name" :label="locale.name" required :disabled="!editable" :error="errors.name" />
        <PhInput id="attendee-badge" v-model="form.badgeName" :label="locale.badgeName" :hint="locale.badgeHint" :disabled="!editable" :error="errors.badgeName" />
        <PhInput id="attendee-company" v-model="form.company" :label="locale.company" :disabled="!editable" :error="errors.company" />
        <PhSelect id="attendee-tshirt" v-model="form.tshirtSize" :label="locale.tshirt" :options="sizeOptions" :disabled="!editable" :error="errors.tshirtSize" />
        <div v-if="canSeePrivate" class="flex flex-col gap-1.5" data-testid="attendee-private">
          <PhTextarea id="attendee-accessibility" v-model="form.accessibility" :label="locale.accessibility" :rows="3" :disabled="!editable" :error="errors.accessibility" />
          <PhBadge status="info" :dot="false" class="self-start">{{ locale.privateNote }}</PhBadge>
        </div>
      </form>

      <section v-if="detail.transfers.length" class="flex flex-col gap-2">
        <h3 class="text-xs font-semibold tracking-wide text-(--ph-ink-3) uppercase">{{ locale.transfers }}</h3>
        <ul class="flex flex-col gap-1.5">
          <li v-for="(transfer, index) in detail.transfers" :key="index">
            <p>{{ locale.transferLine.replace('{de}', transfer.fromName).replace('{para}', transfer.toName) }}</p>
            <p class="text-xs text-(--ph-ink-3)">{{ formatDateTime(transfer.at) }} · {{ transfer.byOrganizer ? locale.byOrganizer : locale.byHolder }}</p>
          </li>
        </ul>
      </section>

      <section class="flex flex-col gap-2">
        <h3 class="text-xs font-semibold tracking-wide text-(--ph-ink-3) uppercase">{{ locale.edits }}</h3>
        <p v-if="!detail.edits.length" class="text-(--ph-ink-3)">{{ locale.noEdits }}</p>
        <ol v-else class="ml-1.5 flex flex-col gap-3 border-l border-(--ph-line) pl-4" data-testid="attendee-edits">
          <li v-for="edit in detail.edits" :key="edit.id" class="relative">
            <span class="absolute top-1.5 -left-[1.3rem] size-2 rounded-full bg-(--ph-brand)" aria-hidden="true" />
            <p v-for="change in edit.changes" :key="change.field">
              <template v-if="change.field === 'accessibility'">{{ locale.accessibilityChanged }}</template>
              <template v-else>{{ locale.changeLine.replace('{campo}', change.label).replace('{de}', show(change.from)).replace('{para}', show(change.to)) }}</template>
            </p>
            <p class="text-xs text-(--ph-ink-3)">{{ formatDateTime(edit.at) }} · {{ edit.actorName === null ? locale.byAttendee : locale.by.replace('{nome}', edit.actorName || '—') }}</p>
          </li>
        </ol>
      </section>
    </div>

    <template v-if="detail && attendee && editable" #footer>
      <div class="flex w-full flex-wrap justify-end gap-2">
        <PhButton variant="secondary" :loading="busy === 'resend'" @click="resendTicket">{{ locale.resend }}</PhButton>
        <PhButton type="submit" form="attendee-form" :loading="busy === 'save'">{{ locale.save }}</PhButton>
      </div>
    </template>
  </PhDrawer>
</template>
