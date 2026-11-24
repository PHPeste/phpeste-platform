<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { centsToInput, parseReais } from '../composables/format'
import locale from '../locales/pt-br'
import type { MutationResult, Tier, TierInput } from '../types'

const props = defineProps<{ tier: Tier | null, save: (tierId: string | null, input: TierInput) => Promise<MutationResult> }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [] }>()

const form = reactive({ name: '', price: '', slots: '' as string | number, benefits: '' })
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)
const busy = ref(false)
const labels: Record<string, string> = { name: locale.tierName, price: locale.tierPrice, slots: locale.tierSlots, benefits: locale.tierBenefits }
const fieldIds: Record<string, string> = { name: 'tier-name', price: 'tier-price', slots: 'tier-slots', benefits: 'tier-benefits' }

watch(open, (value) => {
  if (!value) return
  Object.assign(form, {
    name: props.tier?.name ?? '',
    price: props.tier ? centsToInput(props.tier.priceCents) : '',
    slots: props.tier?.slots ?? '',
    benefits: props.tier?.benefits.join('\n') ?? '',
  })
  errors.value = {}
  message.value = null
})

async function submit() {
  const price = parseReais(form.price)
  const found: Record<string, string> = {}
  if (!form.name.trim()) found.name = locale.required
  if (price === null) found.price = form.price.trim() ? locale.tierPriceInvalid : locale.required
  errors.value = found
  if (Object.keys(found).length) return

  busy.value = true
  const result = await props.save(props.tier?.id ?? null, {
    name: form.name,
    priceCents: price!,
    slots: String(form.slots).trim() === '' ? null : Number(form.slots),
    benefits: form.benefits.split('\n'),
  })
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
  <PhModal v-model="open" :title="tier ? locale.editTier.replace('{nome}', tier.name) : locale.newTier">
    <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
    <form id="tier-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <PhInput id="tier-name" v-model="form.name" :label="locale.tierName" required :error="errors.name" />
      <div class="grid grid-cols-2 gap-3">
        <PhInput id="tier-price" v-model="form.price" :label="locale.tierPrice" required :error="errors.price" />
        <PhInput id="tier-slots" v-model="form.slots" type="number" :label="locale.tierSlots" :hint="locale.tierSlotsHint" :error="errors.slots" />
      </div>
      <PhTextarea id="tier-benefits" v-model="form.benefits" :label="locale.tierBenefits" :hint="locale.tierBenefitsHint" :rows="4" :error="errors.benefits" />
    </form>
    <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
      <PhButton type="submit" form="tier-form" :loading="busy">{{ locale.save }}</PhButton>
    </template>
  </PhModal>
</template>
