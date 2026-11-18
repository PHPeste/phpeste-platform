<script setup lang="ts">
import locale from '../locales/pt-br'
import { STATUS_TONE } from './statusTone'
import type { SpeakerFilter, SpeakerRow } from '../types'

defineProps<{ rows: SpeakerRow[], total: number, counts: Record<SpeakerFilter, number> }>()
const filter = defineModel<SpeakerFilter>('filter', { required: true })
const search = defineModel<string>('search', { required: true })

const FILTERS: SpeakerFilter[] = ['all', 'confirmed', 'pending', 'waitlisted']
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div role="group" :aria-label="locale.filters" class="flex flex-wrap gap-2">
          <button
            v-for="id in FILTERS"
            :key="id"
            type="button"
            class="btn btn-sm"
            :class="filter === id ? 'btn-primary' : 'btn-ghost'"
            :aria-pressed="filter === id"
            @click="filter = id"
          >
            {{ locale.filter[id] }} <span class="tabular-nums opacity-70">{{ counts[id] }}</span>
          </button>
        </div>
        <label class="input w-full sm:w-72">
          <span class="sr-only">{{ locale.search }}</span>
          <input v-model="search" type="search" :placeholder="locale.search">
        </label>
      </div>
      <PhEmptyState v-if="!rows.length && total" :title="locale.emptyFilter">
        <PhButton variant="secondary" @click="filter = 'all'; search = ''">{{ locale.clearFilters }}</PhButton>
      </PhEmptyState>
      <PhEmptyState v-else-if="!rows.length" :title="locale.empty" :description="locale.emptyHint">
        <PhButton variant="secondary" to="/cfp/avaliacao">{{ locale.emptyAction }}</PhButton>
      </PhEmptyState>
      <PhTable v-else :caption="locale.tableCaption">
        <thead>
          <tr>
            <th scope="col">{{ locale.speaker }}</th>
            <th scope="col">{{ locale.state }}</th>
            <th scope="col">{{ locale.talk }}</th>
            <th scope="col">{{ locale.status }}</th>
            <th scope="col"><span class="sr-only">{{ locale.actions }}</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="`${row.userId}-${row.proposal.id}`">
            <td>
              <div class="flex items-center gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-(--ph-accent) text-xs font-semibold text-(--ph-ink)" aria-hidden="true">{{ row.initials }}</span>
                <div class="flex min-w-0 flex-col">
                  <b class="font-medium">{{ row.name }}</b>
                  <span class="truncate text-xs text-(--ph-ink-3)">{{ row.email }}</span>
                </div>
              </div>
            </td>
            <td>
              <span class="font-medium">{{ row.state ?? '—' }}</span>
              <PhBadge v-if="row.nordeste" status="info" :dot="false" class="ml-2">{{ locale.nordeste }}</PhBadge>
            </td>
            <td>
              <span class="flex flex-col">
                <span>{{ row.proposal.title }}</span>
                <span class="text-xs text-(--ph-ink-3)">#{{ row.proposal.number }}<template v-if="row.proposal.track"> · {{ row.proposal.track }}</template></span>
              </span>
            </td>
            <td>
              <PhBadge :status="STATUS_TONE[row.status]">{{ row.statusLabel }}</PhBadge>
              <PhBadge v-if="row.attended" status="ok" :dot="false" class="ml-2">{{ locale.present }}</PhBadge>
            </td>
            <td class="text-right">
              <PhButton variant="ghost" :to="`/palestrantes/${row.userId}`" :aria-label="locale.openSpeaker.replace('{nome}', row.name)">{{ locale.open }}</PhButton>
            </td>
          </tr>
        </tbody>
      </PhTable>
    </div>
  </PhCard>
</template>
