<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { parseReais } from '../composables/format'
import locale from '../locales/pt-br'
import type { Member, MutationResult, NewSponsorInput, Tier } from '../types'

const props = defineProps<{ tiers: Tier[], members: Member[], create: (input: NewSponsorInput) => Promise<MutationResult & { sponsorId?: string }> }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ created: [name: string] }>()

const form = reactive({ name: '', contactName: '', contactEmail: '', tierId: '', value: '', ownerId: '' })
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)
const busy = ref(false)
const labels: Record<string, string> = { name: locale.companyName, contactName: locale.contactName, contactEmail: locale.contactEmail, tierId: locale.tier, value: locale.dealValue, ownerId: locale.owner }
const fieldIds: Record<string, string> = { name: 'new-name', contactName: 'new-contact', contactEmail: 'new-email', tierId: 'new-tier', value: 'new-value', ownerId: 'new-owner' }
const tierOptions = computed(() => [{ value: '', label: locale.tierNone }, ...props.tiers.map((tier) => ({ value: tier.id, label: tier.name }))])
const ownerOptions = computed(() => [{ value: '', label: locale.ownerNone }, ...props.members.map((member) => ({ value: member.id, label: member.name }))])

watch(open, (value) => {
  if (!value) return
  Object.assign(form, { name: '', contactName: '', contactEmail: '', tierId: '', value: '', ownerId: '' })
  errors.value = {}
  message.value = null
})

async function submit() {
  const value = parseReais(form.value)
  const found: Record<string, string> = {}
  if (!form.name.trim()) found.name = locale.required
  if (form.value.trim() !== '' && value === null) found.value = locale.dealValueInvalid
  if (!form.tierId && form.value.trim() === '') found.value = locale.valueOrTier
  errors.value = found
  if (Object.keys(found).length) return

  busy.value = true
  const result = await props.create({ name: form.name, contactName: form.contactName, contactEmail: form.contactEmail, tierId: form.tierId, valueCents: value, ownerId: form.ownerId })
  busy.value = false
  if (result.ok) {
    emit('created', form.name.trim())
    open.value = false
    return
  }
  errors.value = result.fieldErrors
  message.value = Object.keys(result.fieldErrors).length ? null : result.message
}
</script>

<template>
  <PhModal v-model="open" :title="locale.newCompanyTitle">
    <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
    <form id="new-sponsor-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <PhInput id="new-name" v-model="form.name" :label="locale.companyName" required :error="errors.name" />
      <div class="grid gap-3 sm:grid-cols-2">
        <PhInput id="new-contact" v-model="form.contactName" :label="locale.contactName" :error="errors.contactName" />
        <PhInput id="new-email" v-model="form.contactEmail" type="email" :label="locale.contactEmail" :error="errors.contactEmail" />
      </div>
      <div class="grid gap-3 sm:grid-cols-2">
        <PhSelect id="new-tier" v-model="form.tierId" :label="locale.tier" :options="tierOptions" :error="errors.tierId" />
        <PhInput id="new-value" v-model="form.value" :label="locale.dealValue" :hint="locale.dealValueHint" :error="errors.value" />
      </div>
      <PhSelect id="new-owner" v-model="form.ownerId" :label="locale.owner" :options="ownerOptions" :error="errors.ownerId" />
    </form>
    <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
      <PhButton type="submit" form="new-sponsor-form" :loading="busy">{{ locale.createConfirm }}</PhButton>
    </template>
  </PhModal>
</template>
