<script setup lang="ts">
import { computed } from 'vue'
import { formatDateTime } from '../composables/format'
import locale from '../locales/pt-br'
import type { WebhookEvent } from '../types'
import type { BadgeStatus } from '@/application/shared/components/ui/Badge.vue'

const props = defineProps<{ events: WebhookEvent[], state: 'loading' | 'ready' | 'error', status: string, page: number, pageCount: number, disabled: boolean }>()
const emit = defineEmits<{ filter: [status: string], page: [page: number], reprocess: [event: WebhookEvent], retry: [] }>()

const TONE: Record<WebhookEvent['status'], BadgeStatus> = { received: 'info', processing: 'info', processed: 'ok', ignored: 'neutral', failed: 'danger' }
const statusOptions = [{ value: '', label: locale.allEvents }, ...Object.entries(locale.eventStatuses).map(([value, label]) => ({ value, label }))]
const statusModel = computed({ get: () => props.status, set: (value: string) => emit('filter', value) })
const pageModel = computed({ get: () => props.page, set: (value: number) => emit('page', value) })
</script>

<template>
  <PhCard :title="locale.events">
    <div class="flex flex-col gap-3" data-testid="webhook-events">
      <div class="w-56">
        <PhSelect v-model="statusModel" :label="locale.eventsFilter" :options="statusOptions" />
      </div>
      <PhSkeleton v-if="state === 'loading'" :lines="4" />
      <PhLoadError v-else-if="state === 'error'" :title="locale.eventsError" @retry="emit('retry')" />
      <PhEmptyState v-else-if="!events.length" :title="status ? locale.noEventsFilter : locale.noEvents" :description="status ? undefined : locale.noEventsHint" />
      <template v-else>
        <PhTable :caption="locale.eventsCaption">
          <thead>
            <tr>
              <th scope="col">{{ locale.colReceived }}</th>
              <th scope="col">{{ locale.colType }}</th>
              <th scope="col">{{ locale.colCharge }}</th>
              <th scope="col">{{ locale.colStatus }}</th>
              <th scope="col">{{ locale.colResult }}</th>
              <th scope="col"><span class="sr-only">{{ locale.reprocess }}</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="event in events" :key="event.id">
              <td class="text-sm tabular-nums">{{ formatDateTime(event.receivedAt) }}</td>
              <td>
                <span class="block font-mono text-sm">{{ event.type }}</span>
                <span class="block text-xs text-(--ph-ink-3)">{{ event.eventId }}</span>
              </td>
              <td class="font-mono text-xs">{{ event.chargeId ?? '—' }}</td>
              <td>
                <PhBadge :status="TONE[event.status]">{{ event.statusLabel }}</PhBadge>
                <span class="block text-xs text-(--ph-ink-3)">{{ locale.attempts.replace('{n}', String(event.attempts)) }}</span>
              </td>
              <td class="max-w-72 text-sm text-(--ph-ink-2)">
                {{ event.result ?? '' }}
                <span v-if="event.nextAttemptAt" class="block text-xs text-(--ph-ink-3)">{{ locale.nextAttempt.replace('{data}', formatDateTime(event.nextAttemptAt)) }}</span>
              </td>
              <td class="text-right">
                <PhButton v-if="event.canReprocess && !disabled" variant="ghost" :aria-label="locale.reprocessLabel.replace('{id}', event.eventId)" @click="emit('reprocess', event)">{{ locale.reprocess }}</PhButton>
              </td>
            </tr>
          </tbody>
        </PhTable>
        <div v-if="pageCount > 1" class="flex justify-end">
          <PhPagination v-model:page="pageModel" :page-count="pageCount" />
        </div>
      </template>
    </div>
  </PhCard>
</template>
