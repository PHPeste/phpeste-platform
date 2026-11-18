<script setup lang="ts">
import { ref } from 'vue'
import type { BadgeStatus } from '@/application/shared/components/ui/Badge.vue'
import locale from '../locales/pt-br'
import { formatScore } from '../composables/useCfpReview'
import type { ItemState, QueueFilter, ReviewItem } from '../types'

const props = defineProps<{ items: ReviewItem[], total: number, counts: Record<QueueFilter, number>, selectedId: string | null, organizer: boolean }>()
const filter = defineModel<QueueFilter>('filter', { required: true })
const search = defineModel<string>('search', { required: true })
const emit = defineEmits<{ select: [id: string] }>()

const TONE: Record<ItemState, BadgeStatus> = { pending: 'neutral', draft: 'info', reviewed: 'ok', tie: 'warn' }
const filters = (): QueueFilter[] => (props.organizer ? ['all', 'pending', 'reviewed', 'tie'] : ['all', 'pending', 'reviewed'])
const searchInput = ref<HTMLInputElement | null>(null)

function line(item: ReviewItem) {
  if (!props.organizer) {
    if (item.state === 'reviewed') return locale.mySubmitted
    return item.state === 'draft' ? locale.myDraft : locale.noScore
  }
  if (item.score === null) return locale.noScore
  const template = item.reviewsSubmitted === 1 ? locale.scoreLineOne : locale.scoreLine
  return template.replace('{nota}', formatScore(item.score)).replace('{n}', String(item.reviewsSubmitted))
}

defineExpose({ focusSearch: () => searchInput.value?.focus() })
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-3">
      <label class="input w-full">
        <span class="sr-only">{{ locale.search }}</span>
        <input ref="searchInput" v-model="search" type="search" :placeholder="locale.search" data-shortcut-search>
      </label>
      <div role="group" :aria-label="locale.filters" class="flex flex-wrap gap-2">
        <button
          v-for="id in filters()"
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
      <p v-if="!items.length" class="py-4 text-sm text-(--ph-ink-2)">{{ total ? locale.emptyFilter : organizer ? locale.emptyOrg : locale.emptyQueue }}</p>
      <ul v-else :aria-label="locale.queue" class="-mx-2 flex max-h-[70vh] flex-col overflow-y-auto">
        <li v-for="item in items" :key="item.id">
          <button
            type="button"
            class="flex w-full flex-col gap-1 rounded-(--ph-radius) px-3 py-2.5 text-left hover:bg-(--ph-surface-2)"
            :class="{ 'bg-(--ph-surface-2) ring-2 ring-(--ph-accent)': item.id === selectedId }"
            :aria-current="item.id === selectedId ? 'true' : undefined"
            :data-proposal="item.number"
            @click="emit('select', item.id)"
          >
            <span class="flex items-center justify-between gap-2 text-xs text-(--ph-ink-3)">
              <span class="truncate">#{{ item.number }}<template v-if="item.track"> · {{ item.track }}</template></span>
              <PhBadge :status="TONE[item.state]">{{ locale.state[item.state] }}</PhBadge>
            </span>
            <b class="font-medium">{{ item.title }}</b>
            <span class="text-xs text-(--ph-ink-2)">{{ line(item) }}</span>
          </button>
        </li>
      </ul>
    </div>
  </PhCard>
</template>
