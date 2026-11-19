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
const { orders: list, page, pageCount, total, state, filtered, detail, detailState } = orders
const toast = useToast()

const statusInput = ref('')
const queryInput = ref('')
const drawerOpen = ref(false)
const exporting = ref(false)

const statusOptions = [{ value: '', label: locale.allStatuses }, ...Object.entries(locale.statuses).map(([value, label]) => ({ value, label }))]
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

    <form class="flex flex-wrap items-end gap-3" role="search" @submit.prevent="applyFilters">
      <div class="min-w-64 grow">
        <PhInput v-model="queryInput" type="search" :label="locale.search" :placeholder="locale.searchPlaceholder" />
      </div>
      <div class="w-56">
        <PhSelect v-model="statusInput" :label="locale.statusFilter" :options="statusOptions" @update:model-value="applyFilters" />
      </div>
      <PhButton type="submit" variant="secondary">{{ locale.search }}</PhButton>
    </form>

    <PhPageSkeleton v-if="state === 'loading'" :cards="1" :lines="8" />
    <PhLoadError v-else-if="state === 'error'" :title="locale.loadError" @retry="orders.load" />
    <PhEmptyState v-else-if="!list.length && filtered" :title="locale.noMatches" :description="locale.noMatchesHint">
      <PhButton variant="secondary" @click="clearFilters">{{ locale.clearFilters }}</PhButton>
    </PhEmptyState>
    <PhEmptyState v-else-if="!list.length" :title="locale.noOrders" :description="locale.noOrdersHint">
      <PhButton variant="secondary" to="/ingressos">{{ locale.goTickets }}</PhButton>
    </PhEmptyState>

    <PhCard v-else>
      <div class="flex flex-col gap-3">
        <p class="text-sm text-(--ph-ink-3) tabular-nums">{{ locale.total.replace('{n}', String(total)) }}</p>
        <PhTable :caption="locale.tableCaption">
          <thead>
            <tr>
              <th scope="col">{{ locale.colNumber }}</th>
              <th scope="col">{{ locale.colBuyer }}</th>
              <th scope="col" class="text-right">{{ locale.colTickets }}</th>
              <th scope="col" class="text-right">{{ locale.colTotal }}</th>
              <th scope="col">{{ locale.colStatus }}</th>
              <th scope="col">{{ locale.colDate }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in list" :key="order.id" class="hover">
              <td>
                <button type="button" class="link font-medium tabular-nums" :aria-label="locale.open.replace('{n}', String(order.number))" @click="openOrder(order.id)">#{{ order.number }}</button>
              </td>
              <td>
                <span class="block font-medium">{{ order.buyerName }}</span>
                <span class="block text-xs text-(--ph-ink-3)">{{ order.buyerEmail }}</span>
              </td>
              <td class="text-right tabular-nums">{{ order.activeTickets }}</td>
              <td class="text-right tabular-nums">
                {{ formatCents(order.totalCents) }}
                <span v-if="order.refundedCents > 0" class="block text-xs text-(--ph-ink-3)">{{ locale.refundedPart.replace('{valor}', formatCents(order.refundedCents)) }}</span>
              </td>
              <td>
                <PhBadge :status="ORDER_TONE[order.status]">{{ order.statusLabel }}</PhBadge>
                <span v-if="order.externalPlatform" class="block text-xs text-(--ph-ink-3)">{{ locale.imported.replace('{plataforma}', order.externalPlatform) }}</span>
              </td>
              <td class="text-sm tabular-nums">{{ formatDateTime(order.createdAt) }}</td>
            </tr>
          </tbody>
        </PhTable>
        <div v-if="pageCount > 1" class="flex justify-end">
          <PhPagination v-model:page="currentPage" :page-count="pageCount" />
        </div>
      </div>
    </PhCard>

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
