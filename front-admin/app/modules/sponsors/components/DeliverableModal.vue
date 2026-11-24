<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import locale from '../locales/pt-br'
import type { DeliverableInput, Member, MutationResult } from '../types'

const props = defineProps<{ members: Member[], save: (input: DeliverableInput) => Promise<MutationResult> }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [] }>()

const form = reactive({ title: '', ownerId: '', dueOn: '' })
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)
const busy = ref(false)
const owners = computed(() => [{ value: '', label: locale.ownerNone }, ...props.members.map((member) => ({ value: member.id, label: member.name }))])
const labels: Record<string, string> = { title: locale.deliverableTitle, ownerId: locale.colOwner, dueOn: locale.deliverableDue }
const fieldIds: Record<string, string> = { title: 'deliverable-title', ownerId: 'deliverable-owner', dueOn: 'deliverable-due' }

watch(open, (value) => {
  if (!value) return
  Object.assign(form, { title: '', ownerId: '', dueOn: '' })
  errors.value = {}
  message.value = null
})

async function submit() {
  if (form.title.trim().length < 2) {
    errors.value = { title: locale.required }
    return
  }
  busy.value = true
  const result = await props.save({ ...form })
  busy.value = false
  if (result.ok) {
    emit('saved')
    open.value = false
    return
  }
  errors.value = result.fieldErrors
  message.value = Object.keys(result.fieldErrors).length ? null : result.message
}
</script>

<template>
  <PhModal v-model="open" :title="locale.deliverableModal">
    <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
    <form id="deliverable-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <PhInput id="deliverable-title" v-model="form.title" :label="locale.deliverableTitle" required :error="errors.title" />
      <div class="grid grid-cols-2 gap-3">
        <PhSelect id="deliverable-owner" v-model="form.ownerId" :label="locale.colOwner" :options="owners" :error="errors.ownerId" />
        <PhInput id="deliverable-due" v-model="form.dueOn" type="date" :label="locale.deliverableDue" :error="errors.dueOn" />
      </div>
    </form>
    <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
      <PhButton type="submit" form="deliverable-form" :loading="busy">{{ locale.save }}</PhButton>
    </template>
  </PhModal>
</template>
