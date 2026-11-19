<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import OrderDrawer from './OrderDrawer.vue'
import { ORDER_TONE } from './statusTone'
import { useOrders } from '../composables/useOrders'
import { formatCents, formatDateTime } from '../composables/format'
import locale from '../locales/pt-br'

const props = defineProps<{ editionId: string, readOnly: boolean, canManage: boolean, canRefund: boolean }>()

const orders = useOrders(props.editionId)
const { orders: list, summary, page, pageCount, total, state, filtered, detail, detailState } = orders
const toast = useToast()

const statusInput = ref('')
const queryInput = ref('')
const drawerOpen = ref(false)
const exporting = ref(false)

const chips = Object.entries(locale.chips).map(([value, label]) => ({ value, label }))
const percent = new Intl.NumberFormat('pt-BR', { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1 })
const feeShare = computed(() => (summary.value && summary.value.grossCents > 0 ? locale.kpiFeeShare.replace('{p}', percent.format(summary.value.gatewayFeeCents / summary.value.grossCents)) : undefined))

function pickStatus(value: string) {
  statusInput.value = value
  applyFilters()
}
const currentPage = computed({ get: () => page.value, set: (value: number) => orders.goTo(value) })

function applyFilters() {
  orders.search(statusInput.value, queryInput.value)
}

function clearFilters() {
  statusInput.value = ''
  queryInput.value = ''
  applyFilters()
}

function openOrder(id: string) {
  drawerOpen.value = true
  orders.open(id)
}

async function exportCsv() {
  exporting.value = true
  const file = await orders.exportCsv()
  exporting.value = false
  if (!file) {
    toast.error(locale.genericError)
    return
  }
  const url = URL.createObjectURL(file)
  const link = document.createElement('a')
  link.href = url
  link.download = `pedidos-${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
  URL.revokeObjectURL(url)
  toast.success(locale.exported)
}

onMounted(() => orders.load())
</script>

<template>
  <section class="flex flex-col gap-6">
    <header class="flex flex-wrap items-end justify-between gap-3">
      <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl leading-tight font-semibold">{{ locale.title }}</h1>
        <p class="text-(--ph-ink-2)">{{ locale.subtitle }}</p>
      </div>
      <PhButton variant="secondary" :loading="exporting" :disabled="state !== 'ready' || total === 0" @click="exportCsv">{{ locale.exportCsv }}</PhButton>
    </header>

    <p v-if="readOnly" role="status" class="alert alert-info alert-soft text-sm">{{ locale.readOnly }}</p>

    <div v-if="summary" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4" data-testid="orders-summary">
      <PhKpi :label="locale.kpiPaid" :value="summary.paid" />
      <PhKpi :label="locale.kpiAwaiting" :value="summary.awaiting" :hint="summary.awaitingExpiringToday ? locale.kpiExpiringToday.replace('{n}', String(summary.awaitingExpiringToday)) : undefined" />
      <PhKpi :label="locale.kpiRefunds" :value="summary.refunds" :hint="summary.refundedCents ? formatCents(summary.refundedCents) : undefined" />
      <PhKpi :label="locale.kpiFee" :value="formatCents(summary.gatewayFeeCents)" :hint="feeShare" />
    </div>

    <section class="card overflow-hidden border border-base-300 bg-base-100">
      <div class="flex flex-wrap items-center gap-2.5 border-b border-(--ph-line) px-5 py-4">
        <form class="w-full max-w-75" role="search" @submit.prevent="applyFilters">
          <input v-model="queryInput" type="search" class="input w-full" :placeholder="locale.searchPlaceholder" :aria-label="locale.searchLabel">
        </form>
        <div class="flex flex-wrap gap-2.5" role="group" :aria-label="locale.filterLabel">
          <PhChip v-for="chip in chips" :key="chip.value" :model-value="statusInput === chip.value" @update:model-value="pickStatus(chip.value)">{{ chip.label }}</PhChip>
        </div>
      </div>

      <div v-if="state !== 'ready' || !list.length" class="p-5">
        <PhPageSkeleton v-if="state === 'loading'" :cards="1" :lines="8" />
        <PhLoadError v-else-if="state === 'error'" :title="locale.loadError" @retry="orders.load" />
        <PhEmptyState v-else-if="filtered" :title="locale.noMatches" :description="locale.noMatchesHint">
          <PhButton variant="secondary" @click="clearFilters">{{ locale.clearFilters }}</PhButton>
        </PhEmptyState>
        <PhEmptyState v-else :title="locale.noOrders" :description="locale.noOrdersHint">
          <PhButton variant="secondary" to="/ingressos">{{ locale.goTickets }}</PhButton>
        </PhEmptyState>
      </div>

      <template v-else>
        <PhTable :caption="locale.tableCaption">
          <thead>
            <tr>
              <th scope="col">{{ locale.colNumber }}</th>
              <th scope="col">{{ locale.colBuyer }}</th>
              <th scope="col">{{ locale.colItems }}</th>
              <th scope="col" class="text-right">{{ locale.colTotal }}</th>
              <th scope="col">{{ locale.colStatus }}</th>
              <th scope="col">{{ locale.colDate }}</th>
              <th scope="col"><span class="sr-only">{{ locale.openButton }}</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in list" :key="order.id" class="hover">
              <td class="font-medium tabular-nums">#{{ order.number }}</td>
              <td>
                <span class="block font-medium">{{ order.buyerName }}</span>
                <span class="block text-xs text-(--ph-ink-3)">{{ order.buyerEmail }}</span>
              </td>
              <td class="text-sm">{{ order.itemsSummary || '—' }}</td>
              <td class="text-right tabular-nums">
                {{ formatCents(order.totalCents) }}
                <span v-if="order.refundedCents > 0" class="block text-xs text-(--ph-ink-3)">{{ locale.refundedPart.replace('{valor}', formatCents(order.refundedCents)) }}</span>
              </td>
              <td>
                <PhBadge :status="ORDER_TONE[order.status]">{{ order.statusLabel }}</PhBadge>
                <span v-if="order.externalPlatform" class="block text-xs text-(--ph-ink-3)">{{ locale.imported.replace('{plataforma}', order.externalPlatform) }}</span>
              </td>
              <td class="text-sm tabular-nums">{{ formatDateTime(order.createdAt) }}</td>
              <td class="text-right">
                <button type="button" class="btn btn-ghost btn-sm border-base-300" :aria-label="locale.open.replace('{n}', String(order.number))" @click="openOrder(order.id)">{{ locale.openButton }}</button>
              </td>
            </tr>
          </tbody>
        </PhTable>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-(--ph-line) px-5 py-3.5">
          <span class="text-xs text-(--ph-ink-3) tabular-nums">{{ locale.showing.replace('{n}', String(list.length)).replace('{total}', String(total)) }}</span>
          <PhPagination v-if="pageCount > 1" v-model:page="currentPage" :page-count="pageCount" />
        </div>
      </template>
    </section>

    <OrderDrawer
      v-model="drawerOpen"
      :detail="detail"
      :state="detailState"
      :disabled="readOnly"
      :can-manage="canManage"
      :can-refund="canRefund"
      :cancel="orders.cancel"
      :mark-paid="orders.markPaid"
      :remind="orders.remind"
      :refund="orders.refund"
      :settle="orders.settle"
      @retry="detail ? orders.open(detail.order.id) : undefined"
      @update:model-value="!$event && orders.close()"
    />
  </section>
</template>
