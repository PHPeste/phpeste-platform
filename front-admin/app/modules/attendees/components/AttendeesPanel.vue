<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import AttendeeDrawer from './AttendeeDrawer.vue'
import ReminderModal from './ReminderModal.vue'
import { useAttendees } from '../composables/useAttendees'
import locale from '../locales/pt-br'
import type { FilterValue } from '../types'

const props = defineProps<{ editionId: string, readOnly: boolean, canManage: boolean }>()

const attendees = useAttendees(props.editionId, { private: props.canManage })
const { attendees: list, summary, page, pageCount, total, state, filtered, detail, detailState } = attendees
const toast = useToast()

const filterInput = ref<FilterValue>('')
const queryInput = ref('')
const drawerOpen = ref(false)
const reminderOpen = ref(false)
const exporting = ref(false)

// Financeiro não vê acessibilidade: o filtro e a coluna somem para ele.
const chips = computed(() => Object.entries(locale.chips)
  .filter(([value]) => props.canManage || value !== 'accessibility')
  .map(([value, label]) => ({ value: value as FilterValue, label })))
const currentPage = computed({ get: () => page.value, set: (value: number) => attendees.goTo(value) })

function initials(name: string) {
  const parts = name.trim().split(/\s+/)
  return ((parts[0]?.[0] ?? '') + (parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : '')).toUpperCase()
}

function pickFilter(value: FilterValue) {
  filterInput.value = value
  applyFilters()
}

function applyFilters() {
  attendees.search(filterInput.value, queryInput.value)
}

function clearFilters() {
  filterInput.value = ''
  queryInput.value = ''
  applyFilters()
}

function openAttendee(id: string) {
  drawerOpen.value = true
  attendees.open(id)
}

async function exportBadges() {
  exporting.value = true
  const file = await attendees.badges()
  exporting.value = false
  if (!file) {
    toast.error(locale.genericError)
    return
  }
  const url = URL.createObjectURL(file)
  const link = document.createElement('a')
  link.href = url
  link.download = `crachas-${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
  URL.revokeObjectURL(url)
  toast.success(locale.badgesExported)
}

onMounted(() => attendees.load())
</script>

<template>
  <section class="flex flex-col gap-6">
    <header class="flex flex-wrap items-end justify-between gap-3">
      <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl leading-tight font-semibold">{{ locale.title }}</h1>
        <p class="text-(--ph-ink-2)">{{ locale.subtitle }}</p>
      </div>
      <div class="flex flex-wrap gap-3">
        <PhButton v-if="canManage && !readOnly" variant="secondary" :disabled="!summary || summary.missing === 0" @click="reminderOpen = true">{{ locale.remindButton }}</PhButton>
        <PhButton variant="secondary" :loading="exporting" :disabled="!summary || summary.participants === 0" @click="exportBadges">{{ locale.badgesButton }}</PhButton>
      </div>
    </header>

    <p v-if="readOnly" role="status" class="alert alert-info alert-soft text-sm">{{ locale.readOnly }}</p>

    <div v-if="summary" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4" data-testid="attendees-summary">
      <PhKpi :label="locale.kpiParticipants" :value="summary.participants" :hint="locale.kpiParticipantsHint" />
      <PhKpi :label="locale.kpiComplete" :value="summary.complete" :hint="summary.missing ? locale.kpiMissingHint.replace('{n}', String(summary.missing)) : undefined" />
      <PhKpi v-if="summary.accessibility !== null" :label="locale.kpiAccessibility" :value="summary.accessibility" :hint="locale.kpiAccessibilityHint" />
      <PhKpi :label="locale.kpiTransferred" :value="summary.transferred" />
    </div>

    <section class="card overflow-hidden border border-base-300 bg-base-100">
      <div class="flex flex-wrap items-center gap-2.5 border-b border-(--ph-line) px-5 py-4">
        <form class="w-full max-w-75" role="search" @submit.prevent="applyFilters">
          <input v-model="queryInput" type="search" class="input w-full" :placeholder="locale.searchPlaceholder" :aria-label="locale.searchLabel">
        </form>
        <div class="flex flex-wrap gap-2.5" role="group" :aria-label="locale.filterLabel">
          <PhChip v-for="chip in chips" :key="chip.value" :model-value="filterInput === chip.value" @update:model-value="pickFilter(chip.value)">{{ chip.label }}</PhChip>
        </div>
      </div>

      <div v-if="state !== 'ready' || !list.length" class="p-5">
        <PhPageSkeleton v-if="state === 'loading'" :cards="1" :lines="8" />
        <PhLoadError v-else-if="state === 'error'" :title="locale.loadError" @retry="attendees.load" />
        <PhEmptyState v-else-if="filtered" :title="locale.noMatches" :description="locale.noMatchesHint">
          <PhButton variant="secondary" @click="clearFilters">{{ locale.clearFilters }}</PhButton>
        </PhEmptyState>
        <PhEmptyState v-else :title="locale.empty" :description="locale.emptyHint">
          <PhButton variant="secondary" to="/pedidos">{{ locale.goOrders }}</PhButton>
        </PhEmptyState>
      </div>

      <template v-else>
        <PhTable :caption="locale.tableCaption">
          <thead>
            <tr>
              <th scope="col">{{ locale.colAttendee }}</th>
              <th scope="col">{{ locale.colTicket }}</th>
              <th scope="col">{{ locale.colBadge }}</th>
              <th v-if="canManage" scope="col">{{ locale.colAccessibility }}</th>
              <th scope="col">{{ locale.colTshirt }}</th>
              <th scope="col"><span class="sr-only">{{ locale.openButton }}</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="attendee in list" :key="attendee.id" class="hover" :class="{ 'opacity-60': !attendee.active }">
              <td>
                <div class="flex items-center gap-3">
                  <span class="inline-flex size-9 flex-none items-center justify-center rounded-full bg-(--ph-accent) text-[13px] font-semibold text-(--ph-ink)" aria-hidden="true">{{ initials(attendee.name) }}</span>
                  <div class="flex flex-col">
                    <span class="font-medium">{{ attendee.name }}</span>
                    <span class="text-xs text-(--ph-ink-3)">{{ attendee.email }}</span>
                  </div>
                </div>
                <span v-if="attendee.transferredFrom" class="mt-1 block text-xs text-(--ph-ink-3)">{{ locale.transferredFrom.replace('{nome}', attendee.transferredFrom) }}</span>
              </td>
              <td>
                {{ attendee.ticketTypeName }}
                <PhBadge v-if="!attendee.active" status="neutral" :dot="false" class="ml-1">{{ locale.cancelled }}</PhBadge>
              </td>
              <td>
                <PhBadge v-if="attendee.missing.includes('badge_name')" status="warn">{{ locale.missingBadge }}</PhBadge>
                <template v-else>{{ [attendee.badgeName, attendee.company].filter(Boolean).join(' · ') }}</template>
              </td>
              <td v-if="canManage" class="max-w-56 text-sm">{{ attendee.accessibility || '—' }}</td>
              <td>
                <PhBadge v-if="attendee.missing.includes('tshirt_size')" status="warn">{{ locale.missingTshirt }}</PhBadge>
                <template v-else>{{ attendee.tshirtSize ?? '—' }}</template>
              </td>
              <td class="text-right">
                <button type="button" class="btn btn-ghost btn-sm border-base-300" :aria-label="locale.open.replace('{nome}', attendee.name)" @click="openAttendee(attendee.id)">{{ locale.openButton }}</button>
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

    <AttendeeDrawer
      v-model="drawerOpen"
      :detail="detail"
      :state="detailState"
      :disabled="readOnly || !canManage"
      :can-see-private="canManage"
      :save="attendees.save"
      :resend="attendees.resend"
      @retry="detail ? attendees.open(detail.attendee.id) : undefined"
      @update:model-value="!$event && attendees.close()"
    />
    <ReminderModal v-if="canManage" v-model="reminderOpen" :preview="attendees.reminderPreview" :remind="attendees.remind" />
  </section>
</template>
