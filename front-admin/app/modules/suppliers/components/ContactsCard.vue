<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import locale from '../locales/pt-br'
import type { Contact, MutationResult, SupplierLink } from '../types'

const props = defineProps<{
  supplier: SupplierLink
  disabled: boolean
  add: (contact: Omit<Contact, 'id'>) => Promise<MutationResult>
  remove: (contactId: string) => Promise<MutationResult>
}>()

const toast = useToast()
const adding = ref(false)
const busy = ref(false)
const form = reactive({ name: '', role: '', phone: '', email: '' })
const errors = ref<Record<string, string>>({})

function start() {
  Object.assign(form, { name: '', role: '', phone: '', email: '' })
  errors.value = {}
  adding.value = true
}

async function submit() {
  if (form.name.trim().length < 2) {
    errors.value = { name: locale.required }
    return
  }
  busy.value = true
  const result = await props.add({ ...form })
  busy.value = false
  if (result.ok) {
    adding.value = false
    toast.success(locale.contactAdded)
    return
  }
  errors.value = result.fieldErrors
  if (!Object.keys(result.fieldErrors).length) toast.error(result.message)
}

async function removeOne(contact: Contact) {
  const result = await props.remove(contact.id)
  if (result.ok) toast.success(locale.contactRemoved)
  else toast.error(result.message)
}
</script>

<template>
  <section class="card border border-base-300 bg-base-100 p-5" aria-labelledby="contacts-title">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
      <h2 id="contacts-title" class="text-lg font-semibold">{{ locale.contactsSection }}</h2>
      <PhButton v-if="supplier.contactsVisible && !disabled && !adding" variant="secondary" @click="start">{{ locale.addContact }}</PhButton>
    </div>
    <p v-if="!supplier.contactsVisible" class="text-sm text-(--ph-ink-3)">{{ locale.contactsHidden }}</p>
    <template v-else>
      <ul v-if="supplier.contacts.length" class="flex flex-col divide-y divide-(--ph-line) rounded-(--radius-box) border border-(--ph-line)" data-testid="supplier-contacts">
        <li v-for="contact in supplier.contacts" :key="contact.id" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
          <div>
            <p class="font-medium">{{ contact.name }}<span v-if="contact.role" class="font-normal text-(--ph-ink-3)"> · {{ contact.role }}</span></p>
            <p class="text-xs text-(--ph-ink-2)">{{ [contact.phone, contact.email].filter(Boolean).join(' · ') || '—' }}</p>
          </div>
          <button v-if="!disabled" type="button" class="btn btn-ghost btn-sm text-error" :aria-label="locale.removeContact.replace('{nome}', contact.name)" @click="removeOne(contact)">{{ locale.remove }}</button>
        </li>
      </ul>
      <p v-else-if="!adding" class="text-sm text-(--ph-ink-3)">{{ locale.noContacts }}</p>
      <form v-if="adding" class="mt-3 flex flex-col gap-3" novalidate @submit.prevent="submit">
        <div class="grid gap-3 sm:grid-cols-2">
          <PhInput id="contact-name" v-model="form.name" :label="locale.contactName" required :error="errors.name" />
          <PhInput id="contact-role" v-model="form.role" :label="locale.contactRole" :error="errors.role" />
          <PhInput id="contact-phone" v-model="form.phone" type="tel" :label="locale.contactPhone" :error="errors.phone" />
          <PhInput id="contact-email" v-model="form.email" type="email" :label="locale.contactEmail" :error="errors.email" />
        </div>
        <div class="flex justify-end gap-2">
          <PhButton variant="ghost" @click="adding = false">{{ locale.cancel }}</PhButton>
          <PhButton type="submit" :loading="busy">{{ locale.addContact }}</PhButton>
        </div>
      </form>
    </template>
  </section>
</template>
