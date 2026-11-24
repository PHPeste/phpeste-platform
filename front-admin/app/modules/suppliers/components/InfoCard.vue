<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import locale from '../locales/pt-br'
import type { Member, MutationResult, Service, SupplierInput, SupplierLink } from '../types'

const props = defineProps<{ supplier: SupplierLink, members: Member[], disabled: boolean, save: (input: SupplierInput) => Promise<MutationResult> }>()
const emit = defineEmits<{ saved: [] }>()

const form = reactive<SupplierInput>({ name: '', service: 'other', description: '', document: '', website: '', city: '', state: '', ownerId: '' })
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)
const busy = ref(false)
const services = Object.entries(locale.services).map(([value, label]) => ({ value, label }))
const owners = computed(() => [{ value: '', label: locale.ownerNone }, ...props.members.map((member) => ({ value: member.id, label: member.name }))])
const serviceModel = computed({ get: () => form.service as string, set: (value: string) => { form.service = value as Service } })
const labels: Record<string, string> = {
  name: locale.name, service: locale.service, document: locale.document, website: locale.website, city: locale.city, state: locale.state, ownerId: locale.owner, description: locale.description,
}
const fieldIds: Record<string, string> = Object.fromEntries(Object.keys(labels).map((key) => [key, `supplier-${key}`]))

function reset() {
  const s = props.supplier
  Object.assign(form, { name: s.name, service: s.service, description: s.description ?? '', document: s.document ?? '', website: s.website ?? '', city: s.city ?? '', state: s.state ?? '', ownerId: s.ownerId ?? '' })
  errors.value = {}
  message.value = null
}

watch(() => props.supplier, reset, { immediate: true })

async function submit() {
  const found: Record<string, string> = {}
  if (form.name.trim().length < 2) found.name = locale.required
  if (form.state.trim() !== '' && form.state.trim().length !== 2) found.state = locale.stateInvalid
  errors.value = found
  if (Object.keys(found).length) return

  busy.value = true
  const result = await props.save({ ...form })
  busy.value = false
  if (result.ok) {
    emit('saved')
    return
  }
  errors.value = result.fieldErrors
  message.value = Object.keys(result.fieldErrors).length ? null : result.message
}
</script>

<template>
  <section class="card border border-base-300 bg-base-100 p-5" aria-labelledby="info-title">
    <h2 id="info-title" class="text-lg font-semibold">{{ locale.infoSection }}</h2>
    <p class="mb-4 text-xs text-(--ph-ink-3)">{{ locale.infoHint }}</p>
    <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
    <form class="flex flex-col gap-4" novalidate data-testid="supplier-form" @submit.prevent="submit">
      <fieldset class="grid gap-3 sm:grid-cols-2" :disabled="disabled">
        <PhInput id="supplier-name" v-model="form.name" :label="locale.name" required :error="errors.name" />
        <PhSelect id="supplier-service" v-model="serviceModel" :label="locale.service" :options="services" :error="errors.service" />
        <PhInput id="supplier-document" v-model="form.document" :label="locale.document" :error="errors.document" />
        <PhInput id="supplier-website" v-model="form.website" type="url" :label="locale.website" :error="errors.website" />
        <PhInput id="supplier-city" v-model="form.city" :label="locale.city" :error="errors.city" />
        <PhInput id="supplier-state" v-model="form.state" :label="locale.state" :error="errors.state" />
        <PhSelect id="supplier-ownerId" v-model="form.ownerId" :label="locale.owner" :options="owners" :error="errors.ownerId" />
      </fieldset>
      <PhTextarea id="supplier-description" v-model="form.description" :label="locale.description" :hint="locale.descriptionHint" :rows="3" :disabled="disabled" :error="errors.description" />
      <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
      <div v-if="!disabled" class="flex justify-end gap-2">
        <PhButton variant="ghost" @click="reset">{{ locale.cancel }}</PhButton>
        <PhButton type="submit" :loading="busy">{{ locale.save }}</PhButton>
      </div>
    </form>
  </section>
</template>
