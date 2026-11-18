<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import locale from '../locales/pt-br'
import type { SpeakerLogistics } from '../types'

const props = defineProps<{
  logistics: SpeakerLogistics | null
  canViewPrivateData: boolean
  disabled: boolean
  save: (input: { travelPaidByOrganization: boolean, lodgingPaidByOrganization: boolean, organizationNotes: string }) => Promise<{ ok: boolean, message?: string }>
}>()
const emit = defineEmits<{ saved: [] }>()

const form = reactive({ travelPaidByOrganization: false, lodgingPaidByOrganization: false, organizationNotes: '' })
const busy = ref(false)
const error = ref<string | null>(null)

watch(() => props.logistics, (value) => {
  if (!value) return
  form.travelPaidByOrganization = value.travelPaidByOrganization
  form.lodgingPaidByOrganization = value.lodgingPaidByOrganization
  form.organizationNotes = value.organizationNotes
}, { immediate: true })

const origin = computed(() => [props.logistics?.originCity, props.logistics?.originState].filter(Boolean).join(' · '))
const dirty = computed(() => !!props.logistics && (
  form.travelPaidByOrganization !== props.logistics.travelPaidByOrganization
  || form.lodgingPaidByOrganization !== props.logistics.lodgingPaidByOrganization
  || form.organizationNotes !== props.logistics.organizationNotes))

async function submit() {
  busy.value = true
  const result = await props.save({ ...form })
  busy.value = false
  error.value = result.ok ? null : (result.message ?? null)
  if (result.ok) emit('saved')
}
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-5">
      <h2 class="text-lg font-semibold">{{ locale.logisticsTitle }}</h2>
      <p v-if="!logistics" class="text-sm text-(--ph-ink-2)">{{ locale.logisticsNone }}</p>
      <form v-else class="flex flex-col gap-5" @submit.prevent="submit">
        <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-[10rem_1fr]">
          <dt class="text-sm font-medium">{{ locale.origin }}</dt>
          <dd :class="{ 'text-(--ph-ink-3)': !origin }">{{ origin || locale.noValue }}</dd>
          <dt class="text-sm font-medium">{{ locale.travel }}</dt>
          <dd>{{ logistics.needsTravel ? locale.needs : locale.noNeed }}</dd>
          <dt class="text-sm font-medium">{{ locale.lodging }}</dt>
          <dd>{{ logistics.needsLodging ? locale.needs : locale.noNeed }}</dd>
        </dl>
        <div class="flex flex-col gap-3">
          <PhCheckbox v-model="form.travelPaidByOrganization" :label="locale.travelPaid" :disabled="disabled" />
          <PhCheckbox v-model="form.lodgingPaidByOrganization" :label="locale.lodgingPaid" :disabled="disabled" />
        </div>
        <PhTextarea v-model="form.organizationNotes" :label="locale.notes" :hint="locale.notesHint" :rows="3" :disabled="disabled" />

        <section v-if="canViewPrivateData" class="flex flex-col gap-2 rounded-(--ph-radius) border border-(--ph-line) p-4" data-testid="private-data">
          <h3 class="font-semibold">{{ locale.privateTitle }}</h3>
          <p class="text-xs text-(--ph-ink-3)">{{ locale.privateHint }}</p>
          <dl class="grid gap-x-6 gap-y-2 sm:grid-cols-[10rem_1fr]">
            <dt class="text-sm font-medium">{{ locale.accessibility }}</dt>
            <dd :class="{ 'text-(--ph-ink-3)': !logistics.accessibilityNeeds }">{{ logistics.accessibilityNeeds || locale.noValue }}</dd>
            <dt class="text-sm font-medium">{{ locale.dietary }}</dt>
            <dd :class="{ 'text-(--ph-ink-3)': !logistics.dietaryRestrictions }">{{ logistics.dietaryRestrictions || locale.noValue }}</dd>
          </dl>
        </section>

        <p v-if="error" role="alert" class="alert alert-error alert-soft text-sm">{{ error }}</p>
        <PhButton v-if="!disabled" type="submit" class="self-end" :loading="busy" :disabled="!dirty">{{ locale.saveLogistics }}</PhButton>
      </form>
    </div>
  </PhCard>
</template>
