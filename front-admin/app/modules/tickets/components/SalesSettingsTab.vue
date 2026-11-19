<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import locale from '../locales/pt-br'
import type { MutationResult, SalesSettings, SettingsInput } from '../types'

const props = defineProps<{ settings: SalesSettings, disabled: boolean, save: (input: SettingsInput) => Promise<MutationResult> }>()

const toast = useToast()
const form = reactive({ capacity: '' as string | number, reservationMinutes: 30 as string | number, maxTicketsPerBuyer: '' as string | number, feePercent: '' as string })
const errors = ref<Record<string, string>>({})
const busy = ref(false)
const labels: Record<string, string> = { capacity: locale.capacity, reservationMinutes: locale.reservationMinutes, maxTicketsPerBuyer: locale.maxPerBuyer, feeBasisPoints: locale.feePercent }
const fieldIds: Record<string, string> = { capacity: 'sales-capacity', reservationMinutes: 'sales-reservation', maxTicketsPerBuyer: 'sales-max', feeBasisPoints: 'sales-fee' }

watch(() => props.settings, (settings) => {
  Object.assign(form, {
    capacity: settings.capacity ?? '',
    reservationMinutes: settings.reservationMinutes,
    maxTicketsPerBuyer: settings.maxTicketsPerBuyer ?? '',
    feePercent: (settings.feeBasisPoints / 100).toLocaleString('pt-BR', { maximumFractionDigits: 2 }),
  })
}, { immediate: true })

async function submit() {
  const percent = Number(String(form.feePercent).replace(',', '.'))
  if (!Number.isFinite(percent) || percent < 0 || percent > 20) {
    errors.value = { feeBasisPoints: 'Use um percentual de 0 a 20, como 4,99.' }
    return
  }
  busy.value = true
  const result = await props.save({
    capacity: form.capacity === '' ? null : Number(form.capacity),
    reservationMinutes: Number(form.reservationMinutes),
    maxTicketsPerBuyer: form.maxTicketsPerBuyer === '' ? null : Number(form.maxTicketsPerBuyer),
    feeBasisPoints: Math.round(percent * 100),
  })
  busy.value = false
  errors.value = result.ok ? {} : result.fieldErrors
  if (result.ok) toast.success(locale.settingsSaved)
  else if (!Object.keys(result.fieldErrors).length) toast.error(result.message)
}
</script>

<template>
  <PhCard>
    <form class="flex max-w-xl flex-col gap-4" novalidate @submit.prevent="submit">
      <h2 class="text-lg font-semibold">{{ locale.settingsTitle }}</h2>
      <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
      <PhInput id="sales-capacity" v-model="form.capacity" type="number" :label="locale.capacity" :hint="locale.capacityHint" :disabled="disabled" :error="errors.capacity" />
      <PhInput id="sales-reservation" v-model="form.reservationMinutes" type="number" :label="locale.reservationMinutes" :hint="locale.reservationHint" :disabled="disabled" :error="errors.reservationMinutes" />
      <PhInput id="sales-max" v-model="form.maxTicketsPerBuyer" type="number" :label="locale.maxPerBuyer" :hint="locale.maxPerBuyerHint" :disabled="disabled" :error="errors.maxTicketsPerBuyer" />
      <PhInput id="sales-fee" v-model="form.feePercent" :label="locale.feePercent" :hint="locale.feeHint" :disabled="disabled" :error="errors.feeBasisPoints" />
      <div v-if="!disabled">
        <PhButton type="submit" :loading="busy">{{ locale.saveSettings }}</PhButton>
      </div>
    </form>
  </PhCard>
</template>
