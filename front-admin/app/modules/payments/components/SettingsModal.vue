<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import locale from '../locales/pt-br'
import type { Account, MutationResult, SettingsInput } from '../types'

const props = defineProps<{ account: Account, update: (input: SettingsInput) => Promise<MutationResult> }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [] }>()

const form = reactive<SettingsInput>({ holderName: '', methods: [], maxInstallments: 1, pixExpirationMinutes: 30 })
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)
const busy = ref(false)

watch(open, (value) => {
  if (!value) return
  Object.assign(form, { holderName: props.account.holderName, methods: [...props.account.methods], maxInstallments: props.account.maxInstallments, pixExpirationMinutes: props.account.pixExpirationMinutes })
  errors.value = {}
  message.value = null
}, { immediate: true })

const installmentOptions = Array.from({ length: 12 }, (_, index) => ({ value: String(index + 1), label: index === 0 ? locale.noInstallments : locale.installmentsValue.replace('{n}', String(index + 1)) }))
const installments = computed({ get: () => String(form.maxInstallments), set: (value: string) => { form.maxInstallments = Number(value) } })
const pixMinutes = computed({ get: () => form.pixExpirationMinutes, set: (value: string | number) => { form.pixExpirationMinutes = Number(value) } })
const labels = { holderName: locale.fieldHolderName, methods: locale.fieldMethods, maxInstallments: locale.fieldInstallments, pixExpirationMinutes: locale.fieldPixExpiration }

function toggleMethod(method: string, checked: boolean) {
  form.methods = checked ? [...new Set([...form.methods, method])] : form.methods.filter((item) => item !== method)
}

async function submit() {
  busy.value = true
  const result = await props.update({ ...form })
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
  <PhModal v-model="open" :title="locale.settingsTitle">
    <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <PhFormErrors :errors="errors" :labels="labels" />
      <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
      <PhInput v-model="form.holderName" :label="locale.fieldHolderName" :hint="locale.fieldHolderNameHint" :error="errors.holderName" />
      <fieldset class="flex flex-col gap-2">
        <legend class="mb-1 text-sm font-medium">{{ locale.fieldMethods }}</legend>
        <PhCheckbox v-for="(name, key) in locale.methodNames" :key="key" :model-value="form.methods.includes(key)" :label="name" @update:model-value="toggleMethod(key, $event)" />
        <p v-if="errors.methods" class="text-sm text-(--ph-danger)">{{ errors.methods }}</p>
      </fieldset>
      <PhSelect v-if="form.methods.includes('card')" v-model="installments" :label="locale.fieldInstallments" :options="installmentOptions" :hint="locale.installmentsHint" :error="errors.maxInstallments" />
      <PhInput v-if="form.methods.includes('pix')" v-model="pixMinutes" type="number" :label="locale.fieldPixExpiration" :hint="locale.fieldPixExpirationHint" :error="errors.pixExpirationMinutes" />
    </form>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.cancel }}</PhButton>
      <PhButton :loading="busy" @click="submit">{{ locale.saveSettings }}</PhButton>
    </template>
  </PhModal>
</template>
