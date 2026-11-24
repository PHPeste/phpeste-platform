<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { centsToInput, parseReais } from '../composables/format'
import locale from '../locales/pt-br'
import type { Deal, DealInput, Member, MoveResult, MutationResult, Sponsor, SponsorInput, Tier } from '../types'

const props = defineProps<{
  sponsor: Sponsor
  deal: Deal | null
  tiers: Tier[]
  members: Member[]
  disabled: boolean
  saveSponsor: (input: SponsorInput) => Promise<MutationResult>
  saveDeal: (input: DealInput, confirm?: boolean) => Promise<MoveResult>
}>()
const emit = defineEmits<{ saved: [] }>()

const form = reactive({ name: '', website: '', contactName: '', contactEmail: '', contactPhone: '', notes: '', tierId: '', value: '', ownerId: '' })
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)
const confirmText = ref<string | null>(null)
const busy = ref(false)
const tierOptions = computed(() => [{ value: '', label: locale.tierNone }, ...props.tiers.map((tier) => ({ value: tier.id, label: tier.name }))])
const ownerOptions = computed(() => [{ value: '', label: locale.ownerNone }, ...props.members.map((member) => ({ value: member.id, label: member.name }))])
const labels: Record<string, string> = {
  name: locale.companyName, website: locale.website, contactName: locale.contactName, contactEmail: locale.contactEmail, contactPhone: locale.contactPhone,
  notes: locale.notes, tierId: locale.tier, value: locale.dealValue, ownerId: locale.owner,
}
const fieldIds: Record<string, string> = Object.fromEntries(Object.keys(labels).map((key) => [key, `sponsor-${key}`]))

function reset() {
  const { sponsor, deal } = props
  Object.assign(form, {
    name: sponsor.name, website: sponsor.website ?? '', contactName: sponsor.contactName ?? '', contactEmail: sponsor.contactEmail ?? '',
    contactPhone: sponsor.contactPhone ?? '', notes: sponsor.notes ?? '',
    tierId: deal?.tierId ?? '', value: deal ? centsToInput(deal.valueCents) : '', ownerId: deal?.ownerId ?? '',
  })
  errors.value = {}
  message.value = null
  confirmText.value = null
}

watch(() => [props.sponsor, props.deal?.id], reset, { immediate: true })

async function submit() {
  const value = parseReais(form.value)
  const found: Record<string, string> = {}
  if (!form.name.trim()) found.name = locale.required
  if (props.deal && value === null) found.value = form.value.trim() ? locale.dealValueInvalid : locale.required
  errors.value = found
  if (Object.keys(found).length) return

  busy.value = true
  const saved = await props.saveSponsor({ name: form.name, website: form.website, contactName: form.contactName, contactEmail: form.contactEmail, contactPhone: form.contactPhone, notes: form.notes })
  let result: MutationResult | MoveResult = saved
  if (saved.ok && props.deal) {
    const dealResult = await props.saveDeal({ tierId: form.tierId, valueCents: value, ownerId: form.ownerId }, confirmText.value !== null)
    if (!dealResult.ok && dealResult.needsConfirm) {
      busy.value = false
      confirmText.value = dealResult.message
      return
    }
    result = dealResult
  }
  busy.value = false
  if (result.ok) {
    confirmText.value = null
    emit('saved')
    return
  }
  errors.value = result.fieldErrors
  message.value = Object.keys(result.fieldErrors).length ? null : result.message
}
</script>

<template>
  <section class="card border border-base-300 bg-base-100 p-5" :aria-labelledby="'contact-title'">
    <h2 id="contact-title" class="mb-4 text-lg font-semibold">{{ locale.contactSection }}</h2>
    <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
    <form class="flex flex-col gap-4" novalidate data-testid="sponsor-form" @submit.prevent="submit">
      <fieldset class="grid gap-3 sm:grid-cols-2" :disabled="disabled">
        <PhInput id="sponsor-name" v-model="form.name" :label="locale.companyName" required :error="errors.name" />
        <PhInput id="sponsor-website" v-model="form.website" type="url" :label="locale.website" :error="errors.website" />
        <PhInput id="sponsor-contactName" v-model="form.contactName" :label="locale.contactName" :error="errors.contactName" />
        <PhInput id="sponsor-contactEmail" v-model="form.contactEmail" type="email" :label="locale.contactEmail" :error="errors.contactEmail" />
        <PhInput id="sponsor-contactPhone" v-model="form.contactPhone" type="tel" :label="locale.contactPhone" :error="errors.contactPhone" />
      </fieldset>
      <fieldset v-if="deal" class="grid gap-3 sm:grid-cols-3" :disabled="disabled">
        <legend class="mb-2 text-sm font-semibold text-(--ph-ink-2)">{{ locale.dealSection }}</legend>
        <PhSelect id="sponsor-tierId" v-model="form.tierId" :label="locale.tier" :options="tierOptions" :error="errors.tierId" />
        <PhInput id="sponsor-value" v-model="form.value" :label="locale.dealValue" required :error="errors.value" />
        <PhSelect id="sponsor-ownerId" v-model="form.ownerId" :label="locale.owner" :options="ownerOptions" :error="errors.ownerId" />
      </fieldset>
      <PhTextarea id="sponsor-notes" v-model="form.notes" :label="locale.notes" :rows="3" :disabled="disabled" :error="errors.notes" />
      <p v-if="confirmText" role="alert" class="alert alert-warning alert-soft text-sm">{{ confirmText }}</p>
      <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
      <div v-if="!disabled" class="flex justify-end gap-2">
        <PhButton variant="ghost" @click="reset">{{ locale.cancel }}</PhButton>
        <PhButton type="submit" :loading="busy">{{ confirmText ? locale.confirmText : locale.save }}</PhButton>
      </div>
    </form>
  </section>
</template>
