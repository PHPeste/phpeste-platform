<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { centsToInput, parseReais, today } from '../composables/format'
import locale from '../locales/pt-br'
import type { Category, Expense, ExpenseInput, ExpenseStatus, MutationResult, SupplierOption } from '../types'

const props = defineProps<{
  expense: Expense | null
  categories: Category[]
  suppliers: SupplierOption[]
  save: (expenseId: string | null, input: ExpenseInput, receipt: File | null) => Promise<MutationResult>
}>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [] }>()

const form = reactive({ description: '', categoryId: '', supplierId: '', amount: '', dueOn: '', status: 'pending' as ExpenseStatus, paidOn: '', notes: '' })
const receipt = ref<File | null>(null)
const errors = ref<Record<string, string>>({})
const message = ref<string | null>(null)
const busy = ref(false)
const categoryOptions = computed(() => props.categories.map((category) => ({ value: category.id, label: category.name })))
const supplierOptions = computed(() => [{ value: '', label: locale.supplierNone }, ...props.suppliers.map((supplier) => ({ value: supplier.id, label: supplier.name }))])
const statusOptions = Object.entries(locale.statuses).map(([value, label]) => ({ value, label }))
const statusModel = computed({ get: () => form.status as string, set: (value: string) => { form.status = value as ExpenseStatus } })
const labels: Record<string, string> = {
  description: locale.description, categoryId: locale.category, supplierId: locale.supplier, amount: locale.amount, dueOn: locale.dueOn,
  status: locale.status, paidOn: locale.paidOnField, notes: locale.notes, file: locale.receiptField,
}
const fieldIds: Record<string, string> = Object.fromEntries(Object.keys(labels).map((key) => [key, `expense-${key}`]))

watch(open, (value) => {
  if (!value) return
  const expense = props.expense
  Object.assign(form, {
    description: expense?.description ?? '',
    categoryId: expense?.categoryId ?? props.categories[0]?.id ?? '',
    supplierId: expense?.supplierId ?? '',
    amount: expense ? centsToInput(expense.amountCents) : '',
    dueOn: expense?.dueOn ?? '',
    status: expense?.status ?? 'pending',
    paidOn: expense?.paidOn ?? '',
    notes: expense?.notes ?? '',
  })
  receipt.value = null
  errors.value = {}
  message.value = null
})

watch(() => form.status, (status) => {
  if (status === 'paid' && !form.paidOn) form.paidOn = today()
})

function pick(event: Event) {
  receipt.value = (event.target as HTMLInputElement).files?.[0] ?? null
}

async function submit() {
  const amount = parseReais(form.amount)
  const found: Record<string, string> = {}
  if (form.description.trim().length < 2) found.description = locale.required
  if (!form.categoryId) found.categoryId = locale.required
  if (amount === null || amount <= 0) found.amount = form.amount.trim() ? locale.amountInvalid : locale.required
  if (form.status === 'paid' && !form.paidOn) found.paidOn = locale.required
  errors.value = found
  if (Object.keys(found).length) return

  busy.value = true
  const result = await props.save(props.expense?.id ?? null, { ...form, amountCents: amount! }, receipt.value)
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
  <PhModal v-model="open" :title="expense ? locale.expenseTitleEdit : locale.expenseTitleNew">
    <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
    <form id="expense-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <PhInput id="expense-description" v-model="form.description" :label="locale.description" required :error="errors.description" />
      <div class="grid gap-3 sm:grid-cols-2">
        <PhSelect id="expense-categoryId" v-model="form.categoryId" :label="locale.category" :options="categoryOptions" required :error="errors.categoryId" />
        <PhSelect id="expense-supplierId" v-model="form.supplierId" :label="locale.supplier" :options="supplierOptions" :error="errors.supplierId" />
      </div>
      <div class="grid gap-3 sm:grid-cols-2">
        <PhInput id="expense-amount" v-model="form.amount" :label="locale.amount" required :error="errors.amount" />
        <PhInput id="expense-dueOn" v-model="form.dueOn" type="date" :label="locale.dueOn" :error="errors.dueOn" />
      </div>
      <div class="grid gap-3 sm:grid-cols-2">
        <PhSelect id="expense-status" v-model="statusModel" :label="locale.status" :options="statusOptions" :error="errors.status" />
        <PhInput v-if="form.status === 'paid'" id="expense-paidOn" v-model="form.paidOn" type="date" :label="locale.paidOnField" required :error="errors.paidOn" />
      </div>
      <fieldset class="fieldset">
        <legend class="fieldset-legend">{{ locale.receiptField }}</legend>
        <input id="expense-file" type="file" class="file-input w-full" accept=".pdf,.png,.jpg,.jpeg,.xml" :aria-label="locale.receiptField" @change="pick">
        <p class="label text-xs">{{ expense?.receipt ? locale.receiptCurrent.replace('{arquivo}', expense.receipt.name) : locale.receiptHint }}</p>
        <p v-if="errors.file" class="text-xs text-(--ph-danger)">{{ errors.file }}</p>
      </fieldset>
      <PhTextarea id="expense-notes" v-model="form.notes" :label="locale.notes" :rows="2" :error="errors.notes" />
    </form>
    <p v-if="message" role="alert" class="alert alert-error alert-soft text-sm">{{ message }}</p>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
      <PhButton type="submit" form="expense-form" :loading="busy">{{ locale.save }}</PhButton>
    </template>
  </PhModal>
</template>
