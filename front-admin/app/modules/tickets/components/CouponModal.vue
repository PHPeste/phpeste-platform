<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { centsToInput, isoToLocal, parseReais } from '../composables/money'
import locale from '../locales/pt-br'
import type { Coupon, CouponInput, CouponKind, MutationResult, TicketType } from '../types'

const props = defineProps<{
  coupon: Coupon | null
  types: TicketType[]
  save: (coupon: Coupon | null, input: CouponInput) => Promise<MutationResult>
  remove: (coupon: Coupon) => Promise<MutationResult>
}>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [code: string], removed: [code: string] }>()

const form = reactive({ code: '', description: '', kind: 'percent' as CouponKind, value: '' as string, maxUses: '' as string | number, startsAt: '', endsAt: '', ticketTypeIds: [] as string[], active: true })
const busy = ref(false)
const confirming = ref(false)
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)

watch(open, (value) => {
  if (!value) return
  const c = props.coupon
  Object.assign(form, {
    code: c?.code ?? '',
    description: c?.description ?? '',
    kind: c?.kind ?? 'percent',
    value: c ? (c.kind === 'percent' ? String(c.value) : centsToInput(c.value)) : '',
    maxUses: c?.maxUses ?? '',
    startsAt: isoToLocal(c?.startsAt ?? null),
    endsAt: isoToLocal(c?.endsAt ?? null),
    ticketTypeIds: [...(c?.ticketTypeIds ?? [])],
    active: c?.active ?? true,
  })
  confirming.value = false
  errors.value = {}
  message.value = null
})

const codeLocked = computed(() => (props.coupon?.used ?? 0) > 0)
const kindOptions = Object.entries(locale.couponKinds).map(([value, label]) => ({ value, label }))
const labels: Record<string, string> = { code: locale.couponCode, value: locale.discount, maxUses: locale.maxUses, startsAt: locale.startsAt, endsAt: locale.endsAt }
const fieldIds: Record<string, string> = { code: 'coupon-code', value: 'coupon-value', maxUses: 'coupon-max', startsAt: 'coupon-starts', endsAt: 'coupon-ends' }

function amount(): number | null {
  if (form.kind === 'fixed') return parseReais(form.value)
  const percent = Number(form.value)
  return Number.isInteger(percent) && percent >= 1 && percent <= 100 ? percent : null
}

function toggleType(id: string, checked: boolean) {
  form.ticketTypeIds = checked ? [...form.ticketTypeIds, id] : form.ticketTypeIds.filter((t) => t !== id)
}

async function submit() {
  const found: Record<string, string> = {}
  if (!form.code.trim()) found.code = locale.required
  const value = amount()
  if (value === null) found.value = form.kind === 'fixed' ? locale.priceInvalid : 'Use um número inteiro de 1 a 100.'
  if (form.startsAt && form.endsAt && form.endsAt <= form.startsAt) found.endsAt = locale.endsAfterStarts
  errors.value = found
  if (Object.keys(found).length) return

  busy.value = true
  const result = await props.save(props.coupon, {
    code: form.code, description: form.description, kind: form.kind, value: value!, maxUses: form.maxUses === '' ? null : Number(form.maxUses),
    startsAt: form.startsAt, endsAt: form.endsAt, ticketTypeIds: form.ticketTypeIds, active: form.active,
  })
  busy.value = false
  if (result.ok) {
    emit('saved', form.code.trim().toUpperCase())
    open.value = false
    return
  }
  errors.value = result.fieldErrors
  message.value = Object.keys(result.fieldErrors).length ? null : result.message
}

async function confirmRemove() {
  if (!props.coupon) return
  busy.value = true
  const result = await props.remove(props.coupon)
  busy.value = false
  if (result.ok) {
    emit('removed', props.coupon.code)
    open.value = false
    return
  }
  message.value = result.message
}
</script>

<template>
  <PhModal v-model="open" :title="confirming && coupon ? `${locale.removeCoupon} ${coupon.code}?` : (coupon ? locale.editCoupon : locale.newCoupon)">
    <template v-if="!confirming">
      <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
      <form id="coupon-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
        <PhInput id="coupon-code" v-model="form.code" :label="locale.couponCode" :hint="codeLocked ? locale.couponUsedLocked : locale.couponCodeHint" required :disabled="codeLocked" :error="errors.code" />
        <PhInput v-model="form.description" :label="locale.couponDescription" />
        <div class="grid grid-cols-2 gap-3">
          <PhSelect v-model="form.kind" :label="locale.couponKind" :options="kindOptions" />
          <PhInput id="coupon-value" v-model="form.value" :label="form.kind === 'percent' ? locale.couponPercent : locale.couponFixed" required :error="errors.value" />
        </div>
        <PhInput id="coupon-max" v-model="form.maxUses" type="number" :label="locale.maxUses" :hint="locale.maxUsesHint" :error="errors.maxUses" />
        <div class="grid grid-cols-2 gap-3">
          <PhInput id="coupon-starts" v-model="form.startsAt" type="datetime-local" :label="locale.startsAt" :error="errors.startsAt" />
          <PhInput id="coupon-ends" v-model="form.endsAt" type="datetime-local" :label="locale.endsAt" :error="errors.endsAt" />
        </div>
        <fieldset class="flex flex-col gap-2">
          <legend class="text-sm font-medium">{{ locale.couponTypes }}</legend>
          <p v-if="!form.ticketTypeIds.length" class="text-xs text-(--ph-ink-3)">{{ locale.couponAllTypes }}</p>
          <PhCheckbox v-for="type in types" :key="type.id" :model-value="form.ticketTypeIds.includes(type.id)" :label="type.name" @update:model-value="toggleType(type.id, $event)" />
        </fieldset>
        <PhCheckbox v-model="form.active" :label="locale.couponActive" />
        <div v-if="coupon && coupon.used === 0" class="border-t border-(--ph-line) pt-4">
          <PhButton variant="ghost" class="text-error" @click="confirming = true">{{ locale.removeCoupon }}</PhButton>
        </div>
      </form>
    </template>
    <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
    <template #footer>
      <template v-if="!confirming">
        <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
        <PhButton type="submit" form="coupon-form" :loading="busy">{{ locale.saveCoupon }}</PhButton>
      </template>
      <template v-else>
        <PhButton variant="ghost" @click="confirming = false">{{ locale.back }}</PhButton>
        <PhButton variant="danger" :loading="busy" @click="confirmRemove">{{ locale.removeCoupon }}</PhButton>
      </template>
    </template>
  </PhModal>
</template>
