<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { centsToInput, parseReais } from '../composables/format'
import locale from '../locales/pt-br'
import type { MutationResult } from '../types'

const props = defineProps<{
  category: { id: string, name: string, budgetCents: number | null, committedCents: number } | null
  save: (categoryId: string | null, name: string, budgetCents: number | null) => Promise<MutationResult>
  remove: (categoryId: string) => Promise<MutationResult>
}>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ saved: [message: string], failed: [message: string] }>()

const form = reactive({ name: '', budget: '' })
const errors = ref<Record<string, string>>({})
const busy = ref<'save' | 'remove' | null>(null)
const labels: Record<string, string> = { name: locale.categoryName, budget: locale.categoryBudget }
const fieldIds: Record<string, string> = { name: 'category-name', budget: 'category-budget' }

watch(open, (value) => {
  if (!value) return
  form.name = props.category?.name ?? ''
  form.budget = props.category?.budgetCents == null ? '' : centsToInput(props.category.budgetCents)
  errors.value = {}
})

async function submit() {
  const budget = parseReais(form.budget)
  const found: Record<string, string> = {}
  if (form.name.trim().length < 2) found.name = locale.required
  if (form.budget.trim() !== '' && budget === null) found.budget = locale.amountInvalid
  errors.value = found
  if (Object.keys(found).length) return

  busy.value = 'save'
  const result = await props.save(props.category?.id ?? null, form.name, budget)
  busy.value = null
  if (result.ok) {
    emit('saved', locale.categorySaved)
    open.value = false
    return
  }
  errors.value = Object.keys(result.fieldErrors).length ? result.fieldErrors : { name: result.message }
}

async function removeIt() {
  if (!props.category) return
  busy.value = 'remove'
  const result = await props.remove(props.category.id)
  busy.value = null
  if (result.ok) {
    emit('saved', locale.categoryRemoved)
    open.value = false
  } else {
    emit('failed', result.message)
  }
}
</script>

<template>
  <PhModal v-model="open" :title="category ? locale.categoryTitleEdit.replace('{nome}', category.name) : locale.categoryTitleNew">
    <PhFormErrors :errors="errors" :labels="labels" :field-ids="fieldIds" />
    <form id="category-form" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
      <PhInput id="category-name" v-model="form.name" :label="locale.categoryName" required :error="errors.name" />
      <PhInput id="category-budget" v-model="form.budget" :label="locale.categoryBudget" :hint="locale.categoryBudgetHint" :error="errors.budget" />
    </form>
    <template #footer>
      <PhButton v-if="category && category.committedCents === 0" variant="ghost" class="mr-auto text-error" :loading="busy === 'remove'" @click="removeIt">{{ locale.remove }}</PhButton>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
      <PhButton type="submit" form="category-form" :loading="busy === 'save'">{{ locale.save }}</PhButton>
    </template>
  </PhModal>
</template>
