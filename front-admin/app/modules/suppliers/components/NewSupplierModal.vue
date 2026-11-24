<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import locale from '../locales/pt-br'
import type { Member, MutationResult, NewSupplierInput, Service } from '../types'

const props = defineProps<{ members: Member[], create: (input: NewSupplierInput) => Promise<MutationResult & { linkId?: string }> }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ created: [name: string, linkId: string] }>()

const empty = () => ({ name: '', service: 'venue' as Service, description: '', document: '', website: '', city: '', state: '', ownerId: '', contactName: '', contactPhone: '', contactEmail: '' })
const form = reactive(empty())
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)
const busy = ref(false)
const services = Object.entries(locale.services).map(([value, label]) => ({ value, label }))
const owners = computed(() => [{ value: '', label: locale.ownerNone }, ...props.members.map((member) => ({ value: member.id, label: member.name }))])
const serviceModel = computed({ get: () => form.service as string, set: (value: string) => { form.service = value as Service } })
const labels: Record<string, string> = {
  name: locale.name, service: locale.service, document: locale.document, city: locale.city, state: locale.state, ownerId: locale.owner,
  contactName: locale.contactName, phone: locale.contactPhone, email: locale.contactEmail, description: locale.description,
}
const fieldIds: Record<string, string> = Object.fromEntries(Object.keys(labels).map((key) => [key, `new-supplier-${key}`]))

watch(open, (value) => {
  if (!value) return
  Object.assign(form, empty())
  errors.value = {}
  message.value = null
})

async function submit() {
  const found: Record<string, string> = {}
  if (form.name.trim().length < 2) found.name = locale.required
  if (form.state.trim() !== '' && form.state.trim().length !== 2) found.state = locale.stateInvalid
  errors.value = found
  if (Object.keys(found).length) return

  busy.value = true
  const result = await props.create({ ...form })
  busy.value = false
  if (result.ok) {
    emit('created', form.name.trim(), result.linkId ?? '')
    open.value = false
    return
  }
  errors.value = result.fieldErrors
  message.value = Object.keys(result.fieldErrors).length ? null : result.message
}
</script>

<template>
  <PhModal v-model="open" :title="locale.newTitle">
    <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
    <form id="new-supplier-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <div class="grid gap-3 sm:grid-cols-2">
        <PhInput id="new-supplier-name" v-model="form.name" :label="locale.name" required :error="errors.name" />
        <PhSelect id="new-supplier-service" v-model="serviceModel" :label="locale.service" :options="services" :error="errors.service" />
      </div>
      <div class="grid gap-3 sm:grid-cols-[1fr_1fr_5rem]">
        <PhInput id="new-supplier-document" v-model="form.document" :label="locale.document" :error="errors.document" />
        <PhInput id="new-supplier-city" v-model="form.city" :label="locale.city" :error="errors.city" />
        <PhInput id="new-supplier-state" v-model="form.state" :label="locale.state" :error="errors.state" />
      </div>
      <div class="grid gap-3 sm:grid-cols-3">
        <PhInput id="new-supplier-contactName" v-model="form.contactName" :label="locale.contactName" :error="errors.contactName" />
        <PhInput id="new-supplier-phone" v-model="form.contactPhone" type="tel" :label="locale.contactPhone" :error="errors.phone" />
        <PhInput id="new-supplier-email" v-model="form.contactEmail" type="email" :label="locale.contactEmail" :error="errors.email" />
      </div>
      <PhSelect id="new-supplier-ownerId" v-model="form.ownerId" :label="locale.owner" :options="owners" :error="errors.ownerId" />
      <PhTextarea id="new-supplier-description" v-model="form.description" :label="locale.description" :hint="locale.descriptionHint" :rows="2" :error="errors.description" />
    </form>
    <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
      <PhButton type="submit" form="new-supplier-form" :loading="busy">{{ locale.create }}</PhButton>
    </template>
  </PhModal>
</template>
