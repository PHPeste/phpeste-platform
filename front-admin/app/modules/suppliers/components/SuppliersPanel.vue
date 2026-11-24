<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from '@/application/shared/composables/useToast'
import NewSupplierModal from './NewSupplierModal.vue'
import ReuseModal from './ReuseModal.vue'
import { STATUS_TONE } from './statusTone'
import { useSuppliers } from '../composables/useSuppliers'
import { formatCents } from '../composables/format'
import locale from '../locales/pt-br'
import type { SupplierLink } from '../types'

const props = defineProps<{ editionId: string, readOnly: boolean }>()

const list = useSuppliers(props.editionId)
const { suppliers, totals, members, state, filtered, rowSums, undecided, directory, directoryState } = list
const toast = useToast()
const router = useRouter()

const statusInput = ref('')
const serviceInput = ref('')
const queryInput = ref('')
const newOpen = ref(false)
const reuseOpen = ref(false)

const chips = Object.entries(locale.chips).map(([value, label]) => ({ value, label }))
const services = [{ value: '', label: locale.allServices }, ...Object.entries(locale.services).map(([value, label]) => ({ value, label }))]
const percent = new Intl.NumberFormat('pt-BR', { style: 'percent', maximumFractionDigits: 0 })
const hiredHint = computed(() => {
  const hired = totals.value?.byStatus.hired ?? 0
  return hired === 1 ? locale.kpiHiredOne : locale.kpiHired.replace('{n}', String(hired))
})
const shareHint = computed(() => (totals.value && totals.value.quotedCents > 0 ? locale.kpiShare.replace('{p}', percent.format(totals.value.contractedCents / totals.value.quotedCents)) : undefined))
const serviceModel = computed({ get: () => serviceInput.value, set: (value: string) => { serviceInput.value = value; apply() } })

function apply() {
  list.search(statusInput.value, serviceInput.value, queryInput.value)
}

function pickStatus(value: string) {
  statusInput.value = value
  apply()
}

function clear() {
  statusInput.value = ''
  serviceInput.value = ''
  queryInput.value = ''
  apply()
}

const contactOf = (row: SupplierLink) => {
  if (!row.contactsVisible) return locale.hiddenContact
  const first = row.contacts[0]
  return first ? first.phone ?? first.email ?? first.name : locale.noContact
}

function goTo(name: string, linkId: string, message: string) {
  toast.success(message.replace('{nome}', name))
  if (linkId) router.push(`/fornecedores/${linkId}`)
}

onMounted(() => {
  list.load()
  list.loadTeam()
})
</script>

<template>
  <section class="flex flex-col gap-6">
    <header class="flex flex-wrap items-end justify-between gap-3">
      <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl leading-tight font-semibold">{{ locale.title }}</h1>
        <p class="text-(--ph-ink-2)">{{ locale.subtitle }}</p>
      </div>
      <div v-if="!readOnly" class="flex flex-wrap gap-2">
        <PhButton variant="secondary" @click="reuseOpen = true">{{ locale.reuse }}</PhButton>
        <PhButton @click="newOpen = true">{{ locale.newSupplier }}</PhButton>
      </div>
    </header>

    <p v-if="readOnly" role="status" class="alert alert-info alert-soft text-sm">{{ locale.readOnly }}</p>

    <div v-if="totals" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4" data-testid="suppliers-totals" :aria-label="filtered ? locale.kpiFiltered : undefined">
      <PhKpi :label="locale.kpiCount" :value="totals.count" :hint="hiredHint" />
      <PhKpi :label="locale.kpiQuoted" :value="formatCents(totals.quotedCents)" />
      <PhKpi :label="locale.kpiContracted" :value="formatCents(totals.contractedCents)" :hint="shareHint" />
      <PhKpi :label="locale.kpiUndecided" :value="undecided" />
    </div>

    <section class="card overflow-hidden border border-base-300 bg-base-100">
      <div class="flex flex-wrap items-end gap-2.5 border-b border-(--ph-line) px-5 py-4">
        <form class="w-full max-w-75" role="search" @submit.prevent="apply">
          <input v-model="queryInput" type="search" class="input w-full" :placeholder="locale.searchPlaceholder" :aria-label="locale.searchLabel">
        </form>
        <div class="flex flex-wrap gap-2.5" role="group" :aria-label="locale.filterLabel">
          <PhChip v-for="chip in chips" :key="chip.value" :model-value="statusInput === chip.value" @update:model-value="pickStatus(chip.value)">{{ chip.label }}</PhChip>
        </div>
        <div class="ml-auto w-56">
          <PhSelect id="supplier-service-filter" v-model="serviceModel" :label="locale.serviceFilter" :options="services" />
        </div>
      </div>

      <div v-if="state !== 'ready' || !suppliers.length" class="p-5">
        <PhPageSkeleton v-if="state === 'loading'" :cards="1" :lines="6" />
        <PhLoadError v-else-if="state === 'error'" :title="locale.loadError" @retry="list.load" />
        <PhEmptyState v-else-if="filtered" :title="locale.noMatches" :description="locale.noMatchesHint">
          <PhButton variant="secondary" @click="clear">{{ locale.clearFilters }}</PhButton>
        </PhEmptyState>
        <PhEmptyState v-else :title="locale.noSuppliers" :description="locale.noSuppliersHint">
          <template v-if="!readOnly">
            <PhButton variant="secondary" @click="reuseOpen = true">{{ locale.reuse }}</PhButton>
            <PhButton @click="newOpen = true">{{ locale.newSupplier }}</PhButton>
          </template>
        </PhEmptyState>
      </div>

      <PhTable v-else :caption="locale.tableCaption">
        <thead>
          <tr>
            <th scope="col">{{ locale.colSupplier }}</th>
            <th scope="col">{{ locale.colService }}</th>
            <th scope="col">{{ locale.colContact }}</th>
            <th scope="col" class="text-right">{{ locale.colQuoted }}</th>
            <th scope="col" class="text-right">{{ locale.colContracted }}</th>
            <th scope="col">{{ locale.colStatus }}</th>
            <th scope="col"><span class="sr-only">{{ locale.open }}</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in suppliers" :key="row.id" class="hover">
            <td>
              <span class="block font-medium">{{ row.name }}</span>
              <span v-if="row.description" class="block max-w-64 truncate text-xs text-(--ph-ink-3)">{{ row.description }}</span>
            </td>
            <td class="text-sm">{{ row.serviceLabel }}</td>
            <td class="text-sm" :class="{ 'text-(--ph-ink-3)': !row.contactsVisible }">{{ contactOf(row) }}</td>
            <td class="text-right tabular-nums" :class="{ 'text-(--ph-ink-3) line-through': row.status === 'declined' && row.quotedCents }">{{ row.quotedCents === null ? '—' : formatCents(row.quotedCents) }}</td>
            <td class="text-right tabular-nums">{{ row.status === 'hired' && row.contractedCents !== null ? formatCents(row.contractedCents) : '—' }}</td>
            <td><PhBadge :status="STATUS_TONE[row.status]">{{ row.statusLabel }}</PhBadge></td>
            <td class="text-right">
              <NuxtLink :to="`/fornecedores/${row.id}`" class="btn btn-ghost btn-sm border-base-300" :aria-label="locale.openLabel.replace('{nome}', row.name)">{{ locale.open }}</NuxtLink>
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr data-testid="suppliers-list-total">
            <th scope="row" colspan="3">{{ locale.listTotal }}</th>
            <td class="text-right font-semibold tabular-nums">{{ formatCents(rowSums.quotedCents) }}</td>
            <td class="text-right font-semibold tabular-nums">{{ formatCents(rowSums.contractedCents) }}</td>
            <td colspan="2" />
          </tr>
        </tfoot>
      </PhTable>
    </section>

    <NewSupplierModal v-model="newOpen" :members="members" :create="list.create" @created="(name, id) => goTo(name, id, locale.created)" />
    <ReuseModal
      v-model="reuseOpen"
      :entries="directory"
      :state="directoryState"
      :search="list.searchDirectory"
      :reuse="list.reuse"
      @reused="(name, id) => goTo(name, id, locale.reused)"
      @failed="toast.error"
    />
  </section>
</template>
