<script setup lang="ts">
import { computed, reactive, ref, useId, watch } from 'vue'
import ReceiverPreview from './ReceiverPreview.vue'
import locale from '../locales/pt-br'
import type { Account, AccountInput, Driver, MutationResult } from '../types'

const props = defineProps<{
  drivers: Driver[]
  current: Account | null
  create: (input: AccountInput, confirm?: boolean) => Promise<MutationResult>
}>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [] }>()

const blank = (): AccountInput => ({
  driver: props.current?.driver ?? props.drivers[0]?.key ?? '',
  environment: props.current?.environment ?? 'sandbox',
  apiKey: '',
  webhookSecret: '',
  publicKey: '',
  holderType: props.current?.holderType ?? 'individual',
  holderName: props.current?.holderName ?? '',
  holderDocument: '',
  methods: props.current?.methods ?? ['pix', 'card'],
  maxInstallments: props.current?.maxInstallments ?? 1,
  pixExpirationMinutes: props.current?.pixExpirationMinutes ?? 30,
})

const form = reactive<AccountInput>(blank())
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)
const confirmMessage = ref<string | null>(null)
const busy = ref(false)
const uid = useId()
const fieldIds = Object.fromEntries(['driver', 'environment', 'apiKey', 'webhookSecret', 'publicKey', 'holderType', 'holderName', 'holderDocument', 'methods', 'maxInstallments', 'pixExpirationMinutes'].map((key) => [key, `pay-${uid}-${key}`]))

watch(open, (value) => {
  if (!value) return
  Object.assign(form, blank())
  errors.value = {}
  message.value = null
  confirmMessage.value = null
})

const driverOptions = computed(() => props.drivers.map((driver) => ({ value: driver.key, label: driver.label })))
const environmentOptions = Object.entries(locale.environments).map(([value, label]) => ({ value, label }))
const holderOptions = Object.entries(locale.holderTypes).map(([value, label]) => ({ value, label }))
const installmentOptions = Array.from({ length: 12 }, (_, index) => ({ value: String(index + 1), label: index === 0 ? locale.noInstallments : locale.installmentsValue.replace('{n}', String(index + 1)) }))
const needsPublicKey = computed(() => props.drivers.find((driver) => driver.key === form.driver)?.needsPublicKey ?? false)
const installments = computed({ get: () => String(form.maxInstallments), set: (value: string) => { form.maxInstallments = Number(value) } })
const pixMinutes = computed({ get: () => form.pixExpirationMinutes, set: (value: string | number) => { form.pixExpirationMinutes = Number(value) } })

const labels = {
  driver: locale.fieldDriver, environment: locale.fieldEnvironment, apiKey: locale.fieldApiKey, webhookSecret: locale.fieldWebhookSecret,
  publicKey: locale.fieldPublicKey, holderType: locale.fieldHolderType, holderName: locale.fieldHolderName, holderDocument: locale.fieldDocumentCpf,
  methods: locale.fieldMethods, maxInstallments: locale.fieldInstallments, pixExpirationMinutes: locale.fieldPixExpiration,
}

function toggleMethod(method: string, checked: boolean) {
  form.methods = checked ? [...new Set([...form.methods, method])] : form.methods.filter((item) => item !== method)
}

async function submit(confirm = false) {
  busy.value = true
  const result = await props.create({ ...form }, confirm)
  busy.value = false
  if (result.ok) {
    confirmMessage.value = null
    emit('saved')
    open.value = false
    return
  }
  if (result.needsConfirm) {
    confirmMessage.value = result.fieldErrors.confirm ?? result.message
    return
  }
  confirmMessage.value = null
  errors.value = result.fieldErrors
  message.value = Object.keys(result.fieldErrors).length ? null : result.message
}
</script>

<template>
  <PhDrawer v-model="open" :title="current ? locale.formTitleReplace : locale.formTitleNew">
    <form class="flex flex-col gap-4" novalidate @submit.prevent="submit()">
      <p v-if="current" class="alert alert-warning alert-soft text-sm" data-testid="replace-warning">{{ locale.replaceWarning }}</p>
      <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
      <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>

      <div class="grid gap-4 sm:grid-cols-2">
        <PhSelect :id="fieldIds.driver" v-model="form.driver" :label="locale.fieldDriver" :options="driverOptions" :error="errors.driver" />
        <PhSelect :id="fieldIds.environment" v-model="form.environment" :label="locale.fieldEnvironment" :options="environmentOptions" :error="errors.environment" />
      </div>
      <PhInput :id="fieldIds.apiKey" v-model="form.apiKey" type="password" autocomplete="off" :label="locale.fieldApiKey" :hint="locale.fieldApiKeyHint" :error="errors.apiKey" required />
      <PhInput :id="fieldIds.webhookSecret" v-model="form.webhookSecret" type="password" autocomplete="off" :label="locale.fieldWebhookSecret" :hint="locale.fieldWebhookSecretHint" :error="errors.webhookSecret" required />
      <PhInput v-if="needsPublicKey" :id="fieldIds.publicKey" v-model="form.publicKey" type="password" autocomplete="off" :label="locale.fieldPublicKey" :hint="locale.fieldPublicKeyHint" :error="errors.publicKey" required />

      <PhSelect :id="fieldIds.holderType" v-model="form.holderType" :label="locale.fieldHolderType" :options="holderOptions" :error="errors.holderType" />
      <p class="rounded-box bg-(--ph-info-bg) p-3 text-sm text-(--ph-ink-2)" data-testid="holder-guidance">
        {{ form.holderType === 'individual' ? locale.guidanceIndividual : locale.guidanceCompany }}
      </p>
      <PhInput :id="fieldIds.holderName" v-model="form.holderName" :label="locale.fieldHolderName" :hint="locale.fieldHolderNameHint" :error="errors.holderName" required />
      <PhInput
        :id="fieldIds.holderDocument"
        v-model="form.holderDocument"
        :label="form.holderType === 'individual' ? locale.fieldDocumentCpf : locale.fieldDocumentCnpj"
        :hint="locale.fieldDocumentHint"
        :error="errors.holderDocument"
        autocomplete="off"
        required
      />

      <fieldset :id="fieldIds.methods" class="flex flex-col gap-2" tabindex="-1">
        <legend class="mb-1 text-sm font-medium">{{ locale.fieldMethods }}</legend>
        <PhCheckbox v-for="(name, key) in locale.methodNames" :key="key" :model-value="form.methods.includes(key)" :label="name" @update:model-value="toggleMethod(key, $event)" />
        <p v-if="errors.methods" class="text-sm text-(--ph-danger)">{{ errors.methods }}</p>
      </fieldset>
      <div class="grid gap-4 sm:grid-cols-2">
        <PhSelect v-if="form.methods.includes('card')" :id="fieldIds.maxInstallments" v-model="installments" :label="locale.fieldInstallments" :options="installmentOptions" :error="errors.maxInstallments" />
        <PhInput v-if="form.methods.includes('pix')" :id="fieldIds.pixExpirationMinutes" v-model="pixMinutes" type="number" :label="locale.fieldPixExpiration" :hint="locale.fieldPixExpirationHint" :error="errors.pixExpirationMinutes" />
      </div>

      <ReceiverPreview :holder-name="form.holderName" :holder-type="form.holderType" :methods="form.methods" :max-installments="form.maxInstallments" :sandbox="form.environment === 'sandbox'" />
    </form>

    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.cancel }}</PhButton>
      <PhButton :loading="busy" @click="submit()">{{ locale.save }}</PhButton>
    </template>

    <PhModal :model-value="confirmMessage !== null" :title="locale.confirmTitle" @update:model-value="!$event && (confirmMessage = null)">
      <p data-testid="confirm-message">{{ confirmMessage }}</p>
      <template #footer>
        <PhButton variant="ghost" @click="confirmMessage = null">{{ locale.cancel }}</PhButton>
        <PhButton :loading="busy" @click="submit(true)">{{ locale.confirmAction }}</PhButton>
      </template>
    </PhModal>
  </PhDrawer>
</template>
