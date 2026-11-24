<script setup lang="ts">
import { ref, watch } from 'vue'
import locale from '../locales/pt-br'
import type { DirectoryEntry, MutationResult } from '../types'

const props = defineProps<{
  entries: DirectoryEntry[]
  state: 'idle' | 'loading' | 'ready' | 'error'
  search: (text: string) => Promise<void>
  reuse: (supplierId: string, ownerId: string) => Promise<MutationResult & { linkId?: string }>
}>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ reused: [name: string, linkId: string], failed: [message: string] }>()

const text = ref('')
const busy = ref<string | null>(null)

watch(open, (value) => {
  if (!value) return
  text.value = ''
  props.search('')
})

async function bring(entry: DirectoryEntry) {
  busy.value = entry.id
  const result = await props.reuse(entry.id, '')
  busy.value = null
  if (result.ok) {
    emit('reused', entry.name, result.linkId ?? '')
    open.value = false
  } else {
    emit('failed', result.message)
  }
}
</script>

<template>
  <PhModal v-model="open" :title="locale.reuseTitle">
    <p class="text-sm text-(--ph-ink-2)">{{ locale.reuseHint }}</p>
    <form class="mt-3" role="search" @submit.prevent="search(text)">
      <input v-model="text" type="search" class="input w-full" :aria-label="locale.reuseSearch" :placeholder="locale.reuseSearch">
    </form>
    <div class="mt-4" data-testid="supplier-directory">
      <PhSkeleton v-if="state === 'loading'" :lines="3" />
      <p v-else-if="state === 'error'" role="alert" class="alert alert-error alert-soft text-sm">{{ locale.reuseError }}</p>
      <p v-else-if="!entries.length" class="text-sm text-(--ph-ink-3)">{{ locale.reuseEmpty }}</p>
      <ul v-else class="flex flex-col divide-y divide-(--ph-line) rounded-(--radius-box) border border-(--ph-line)">
        <li v-for="entry in entries" :key="entry.id" class="flex flex-wrap items-center justify-between gap-3 px-3 py-2.5">
          <div class="min-w-0">
            <p class="font-medium">{{ entry.name }}</p>
            <p class="text-xs text-(--ph-ink-3)">{{ entry.serviceLabel }}<template v-if="entry.city"> · {{ entry.city }}<template v-if="entry.state">/{{ entry.state }}</template></template></p>
            <p class="text-xs text-(--ph-ink-2)">
              {{ locale.history }}: {{ entry.editions.length ? entry.editions.map((edition) => `${edition.name} (${edition.statusLabel})`).join(', ') : locale.noEditions }}
            </p>
          </div>
          <span v-if="entry.inThisEdition" class="text-xs text-(--ph-ink-3)">{{ locale.alreadyHere }}</span>
          <PhButton v-else variant="secondary" :loading="busy === entry.id" :aria-label="locale.reuseActionLabel.replace('{nome}', entry.name)" @click="bring(entry)">{{ locale.reuseAction }}</PhButton>
        </li>
      </ul>
    </div>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.back }}</PhButton>
    </template>
  </PhModal>
</template>
