<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import BudgetCard from './BudgetCard.vue'
import RevenueCard from './RevenueCard.vue'
import CategoryModal from './CategoryModal.vue'
import ExpenseModal from './ExpenseModal.vue'
import { useFinance, type ExpenseFilter } from '../composables/useFinance'
import { formatCents, formatDay, today } from '../composables/format'
import locale from '../locales/pt-br'
import type { BudgetLine, Expense } from '../types'

const props = defineProps<{ editionId: string, readOnly: boolean }>()

const finance = useFinance(props.editionId)
const { summary, categories, suppliers, expenses, totals, filter, state, expensesState } = finance
const toast = useToast()

const categoryOpen = ref(false)
const editingCategory = ref<{ id: string, name: string, budgetCents: number | null, committedCents: number } | null>(null)
const expenseOpen = ref(false)
const editingExpense = ref<Expense | null>(null)
const removing = ref<Expense | null>(null)
const removeOpen = ref(false)
const busy = ref<string | null>(null)
const exporting = ref<'xlsx' | 'csv' | null>(null)

const chips = Object.entries(locale.chips).map(([value, label]) => ({ value: value as ExpenseFilter, label }))
const pendingHint = computed(() => (summary.value && summary.value.pendingCents > 0 ? locale.kpiPending.replace('{valor}', formatCents(summary.value.pendingCents)) : undefined))
const overdueText = computed(() => {
  const count = summary.value?.overdueCount ?? 0
  if (count === 0) return null
  return count === 1 ? locale.overdueOne : locale.overdue.replace('{n}', String(count))
})

function editCategory(line: BudgetLine | null) {
  editingCategory.value = line ? { id: line.id, name: line.name, budgetCents: line.budgetCents, committedCents: line.committedCents } : null
  categoryOpen.value = true
}

function editExpense(expense: Expense | null) {
  editingExpense.value = expense
  expenseOpen.value = true
}

async function run(key: string, action: () => Promise<{ ok: boolean, message?: string }>, success: string) {
  busy.value = key
  const result = await action()
  busy.value = null
  if (result.ok) toast.success(success)
  else toast.error(result.message ?? locale.genericError)
  return result.ok
}

async function download(expense: Expense) {
  const url = await finance.receiptUrl(expense.id)
  if (!url) {
    toast.error(locale.linkError)
    return
  }
  window.location.assign(url)
}

async function confirmRemove() {
  if (removing.value && await run('remove', () => finance.deleteExpense(removing.value!), locale.removed)) removeOpen.value = false
}

async function exportFile(format: 'xlsx' | 'csv') {
  exporting.value = format
  const file = await finance.exportFile(format)
  exporting.value = null
  if (!file) {
    toast.error(locale.genericError)
    return
  }
  const url = URL.createObjectURL(file)
  const link = document.createElement('a')
  link.href = url
  link.download = format === 'csv' ? `despesas-${today()}.csv` : `financas-${today()}.xlsx`
  link.click()
  URL.revokeObjectURL(url)
  toast.success(locale.exported)
}

onMounted(() => finance.load())
</script>

<template>
  <section class="flex flex-col gap-6">
    <header class="flex flex-wrap items-end justify-between gap-3">
      <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl leading-tight font-semibold">{{ locale.title }}</h1>
        <p class="text-(--ph-ink-2)">{{ locale.subtitle }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <PhButton variant="ghost" :loading="exporting === 'csv'" :disabled="state !== 'ready'" @click="exportFile('csv')">{{ locale.exportCsv }}</PhButton>
        <PhButton variant="secondary" :loading="exporting === 'xlsx'" :disabled="state !== 'ready'" @click="exportFile('xlsx')">{{ locale.exportXlsx }}</PhButton>
      </div>
    </header>

    <p v-if="readOnly" role="status" class="alert alert-info alert-soft text-sm">{{ locale.readOnly }}</p>

    <PhPageSkeleton v-if="state === 'loading'" :cards="4" :lines="4" />
    <PhLoadError v-else-if="state === 'error'" :title="locale.loadError" @retry="finance.load" />

    <template v-else-if="summary">
      <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4" data-testid="finance-kpis">
        <PhKpi :label="locale.kpiRevenue" :value="formatCents(summary.revenueCents)" :hint="locale.kpiRevenueHint" />
        <PhKpi :label="locale.kpiPaid" :value="formatCents(summary.paidCents)" :hint="pendingHint" />
        <PhKpi :label="locale.kpiBalance" :value="formatCents(summary.balanceCents)" :hint="locale.kpiBalanceHint" />
        <PhKpi :label="locale.kpiForecast" :value="formatCents(summary.forecastCents)" :hint="locale.kpiForecastHint" />
      </div>
      <p v-if="overdueText" role="status" class="alert alert-warning alert-soft text-sm">{{ overdueText }}</p>

      <div class="grid gap-6 xl:grid-cols-2">
        <BudgetCard :summary="summary" :disabled="readOnly" @edit="editCategory" />
        <RevenueCard :summary="summary" />
      </div>

      <section class="card overflow-hidden border border-base-300 bg-base-100" aria-labelledby="expenses-title">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-(--ph-line) px-5 py-4">
          <h2 id="expenses-title" class="text-lg font-semibold">{{ locale.expensesTitle }}</h2>
          <PhButton v-if="!readOnly" @click="editExpense(null)">{{ locale.newExpense }}</PhButton>
        </div>
        <div class="flex flex-wrap gap-2.5 border-b border-(--ph-line) px-5 py-3" role="group" :aria-label="locale.filterLabel">
          <PhChip v-for="chip in chips" :key="chip.value" :model-value="filter === chip.value" @update:model-value="finance.filterBy(chip.value)">{{ chip.label }}</PhChip>
        </div>

        <div v-if="expensesState !== 'ready' || !expenses.length" class="p-5">
          <PhPageSkeleton v-if="expensesState === 'loading'" :cards="1" :lines="5" />
          <PhLoadError v-else-if="expensesState === 'error'" :title="locale.loadError" @retry="finance.loadExpenses" />
          <PhEmptyState v-else-if="filter" :title="locale.noMatches">
            <PhButton variant="secondary" @click="finance.filterBy('')">{{ locale.showAll }}</PhButton>
          </PhEmptyState>
          <PhEmptyState v-else :title="locale.noExpenses" :description="locale.noExpensesHint">
            <PhButton v-if="!readOnly" @click="editExpense(null)">{{ locale.newExpense }}</PhButton>
          </PhEmptyState>
        </div>

        <PhTable v-else :caption="locale.tableCaption">
          <thead>
            <tr>
              <th scope="col">{{ locale.colExpense }}</th>
              <th scope="col">{{ locale.colCategory }}</th>
              <th scope="col">{{ locale.colSupplier }}</th>
              <th scope="col" class="text-right">{{ locale.colAmount }}</th>
              <th scope="col">{{ locale.colDue }}</th>
              <th scope="col">{{ locale.colStatus }}</th>
              <th scope="col"><span class="sr-only">{{ locale.colActions }}</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="expense in expenses" :key="expense.id" :class="{ 'opacity-60': expense.status === 'cancelled' }">
              <td>
                <span class="block font-medium">{{ expense.description }}</span>
                <button v-if="expense.receipt" type="button" class="link text-xs" :aria-label="locale.downloadReceipt.replace('{despesa}', expense.description)" @click="download(expense)">{{ locale.receipt }}</button>
              </td>
              <td class="text-sm">{{ expense.categoryName }}</td>
              <td class="text-sm">{{ expense.supplierName ?? '—' }}</td>
              <td class="text-right tabular-nums">{{ formatCents(expense.amountCents) }}</td>
              <td class="text-sm tabular-nums">
                {{ expense.dueOn ? formatDay(expense.dueOn) : '—' }}
                <span v-if="expense.paidOn" class="block text-xs text-(--ph-ink-3)">{{ locale.paidOn.replace('{data}', formatDay(expense.paidOn)) }}</span>
              </td>
              <td>
                <PhBadge :status="expense.overdue ? 'danger' : expense.status === 'paid' ? 'ok' : expense.status === 'pending' ? 'warn' : 'neutral'">{{ expense.statusLabel }}</PhBadge>
              </td>
              <td class="text-right whitespace-nowrap">
                <template v-if="!readOnly">
                  <button
                    v-if="expense.status === 'pending'"
                    type="button"
                    class="btn btn-ghost btn-sm"
                    :aria-label="locale.markPaidLabel.replace('{despesa}', expense.description)"
                    :disabled="busy === `paid-${expense.id}`"
                    @click="run(`paid-${expense.id}`, () => finance.markPaid(expense, today()), locale.markedPaid)"
                  >
                    {{ locale.markPaid }}
                  </button>
                  <button type="button" class="btn btn-ghost btn-sm" :aria-label="locale.editExpense.replace('{despesa}', expense.description)" @click="editExpense(expense)">{{ locale.edit }}</button>
                  <button type="button" class="btn btn-ghost btn-sm text-error" :aria-label="`${locale.removeExpense}: ${expense.description}`" @click="removing = expense; removeOpen = true">{{ locale.remove }}</button>
                </template>
              </td>
            </tr>
          </tbody>
          <tfoot v-if="totals">
            <tr data-testid="expenses-total">
              <th scope="row" colspan="3">{{ locale.listTotal }}</th>
              <td class="text-right font-semibold tabular-nums">{{ formatCents(totals.amountCents) }}</td>
              <td colspan="3" />
            </tr>
          </tfoot>
        </PhTable>
      </section>
    </template>

    <CategoryModal v-model="categoryOpen" :category="editingCategory" :save="finance.saveCategory" :remove="finance.deleteCategory" @saved="toast.success" @failed="toast.error" />
    <ExpenseModal v-model="expenseOpen" :expense="editingExpense" :categories="categories" :suppliers="suppliers" :save="finance.saveExpense" @saved="toast.success(locale.expenseSaved)" />
    <PhModal v-model="removeOpen" :title="locale.removeTitle.replace('{despesa}', removing?.description ?? '')">
      <p>{{ locale.removeText }}</p>
      <template #footer>
        <PhButton variant="ghost" @click="removeOpen = false">{{ locale.back }}</PhButton>
        <PhButton variant="danger" :loading="busy === 'remove'" @click="confirmRemove">{{ locale.remove }}</PhButton>
      </template>
    </PhModal>
  </section>
</template>
