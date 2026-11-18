<script setup lang="ts">
import { ref } from 'vue'
import locale from '../locales/pt-br'
import type { PromotionCandidate, PromotionGroup } from '../types'

defineProps<{ groups: PromotionGroup[], disabled: boolean, promote: (proposalId: string) => Promise<string | null> }>()
const emit = defineEmits<{ promoted: [number: number], failed: [message: string] }>()

const pending = ref<PromotionCandidate | null>(null)
const open = ref(false)
const busy = ref(false)

function ask(candidate: PromotionCandidate) {
  pending.value = candidate
  open.value = true
}

async function confirm(run: (proposalId: string) => Promise<string | null>) {
  if (!pending.value) return
  busy.value = true
  const error = await run(pending.value.proposalId)
  busy.value = false
  open.value = false
  if (error) emit('failed', error)
  else emit('promoted', pending.value.number)
}
</script>

<template>
  <PhCard v-if="groups.length">
    <div class="flex flex-col gap-3">
      <div class="flex flex-col gap-1">
        <h2 class="text-lg font-semibold">{{ locale.promotionsTitle }}</h2>
        <p class="text-sm text-(--ph-ink-2)">{{ locale.promotionsHint }}</p>
      </div>
      <ul class="flex flex-col">
        <li v-for="group in groups" :key="group.track" class="flex flex-wrap items-center justify-between gap-3 border-t border-(--ph-line) py-3 first:border-t-0">
          <div class="flex flex-col">
            <b class="font-medium">{{ (group.openSlots === 1 ? locale.openSlotsOne : locale.openSlots).replace('{trilha}', group.track).replace('{n}', String(group.openSlots)) }}</b>
            <span class="text-sm text-(--ph-ink-2)">
              {{ group.next ? locale.nextInLine.replace('{n}', String(group.next.number)).replace('{titulo}', group.next.title) : locale.noCandidate }}
            </span>
          </div>
          <PhButton v-if="group.next && !disabled" variant="secondary" :aria-label="`${locale.promote} #${group.next.number}`" @click="ask(group.next)">{{ locale.promote }}</PhButton>
        </li>
      </ul>
    </div>
    <PhModal v-model="open" :title="locale.promoteTitle">
      <p>{{ locale.promoteText.replace('{n}', String(pending?.number ?? '')).replace('{titulo}', pending?.title ?? '') }}</p>
      <template #footer>
        <PhButton variant="ghost" @click="open = false">{{ locale.cancel }}</PhButton>
        <PhButton :loading="busy" @click="confirm(promote)">{{ locale.promote }}</PhButton>
      </template>
    </PhModal>
  </PhCard>
</template>
