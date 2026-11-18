<script setup lang="ts">
import { ref } from 'vue'
import locale from '../locales/pt-br'
import type { SaveResult, StatusOption } from '../types'

const props = defineProps<{ statusLabel: string, options: StatusOption[], disabled: boolean, change: (status: string) => Promise<SaveResult> }>()
const emit = defineEmits<{ changed: [label: string], failed: [message: string] }>()

const pending = ref<StatusOption | null>(null)
const open = ref(false)
const busy = ref(false)

function ask(option: StatusOption) {
  pending.value = option
  open.value = true
}

async function confirm() {
  if (!pending.value) return
  busy.value = true
  const result = await props.change(pending.value.status)
  busy.value = false
  open.value = false
  if (result.ok) emit('changed', pending.value.label)
  else emit('failed', result.message)
}
</script>

<template>
  <PhCard>
    <div class="flex flex-col gap-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">{{ locale.stageTitle }}</h2>
        <span class="text-sm">{{ locale.stageCurrent }}: <PhBadge status="info">{{ statusLabel }}</PhBadge></span>
      </div>
      <p v-if="!options.length" class="text-sm text-(--ph-ink-2)">{{ locale.stageFinal }}</p>
      <template v-else>
        <p class="text-sm text-(--ph-ink-2)">{{ locale.stageForwardOnly }}</p>
        <div class="grid gap-4 md:grid-cols-2">
          <article v-for="option in options" :key="option.status" class="flex flex-col gap-3 rounded-(--ph-radius) border border-(--ph-line) p-4" :data-status="option.status">
            <h3 class="font-semibold">{{ option.label }}</h3>
            <div v-if="option.unlocks.length" class="flex flex-col gap-1">
              <span class="text-xs font-semibold tracking-wide text-(--ph-ok) uppercase">{{ locale.unlocks }}</span>
              <ul class="list-disc pl-5 text-sm text-(--ph-ink-2)">
                <li v-for="item in option.unlocks" :key="item">{{ item }}</li>
              </ul>
            </div>
            <div v-if="option.blocks.length" class="flex flex-col gap-1">
              <span class="text-xs font-semibold tracking-wide text-(--ph-danger) uppercase">{{ locale.blocks }}</span>
              <ul class="list-disc pl-5 text-sm text-(--ph-ink-2)">
                <li v-for="item in option.blocks" :key="item">{{ item }}</li>
              </ul>
            </div>
            <PhButton v-if="!disabled" variant="secondary" class="mt-auto self-start" @click="ask(option)">{{ locale.moveTo.replace('{etapa}', option.label) }}</PhButton>
          </article>
        </div>
      </template>
    </div>
    <PhModal v-model="open" :title="locale.moveTitle.replace('{etapa}', pending?.label ?? '')">
      <div v-if="pending" class="flex flex-col gap-3 text-sm">
        <div v-if="pending.unlocks.length">
          <b class="text-(--ph-ok)">{{ locale.unlocks }}</b>
          <ul class="list-disc pl-5"><li v-for="item in pending.unlocks" :key="item">{{ item }}</li></ul>
        </div>
        <div v-if="pending.blocks.length">
          <b class="text-(--ph-danger)">{{ locale.blocks }}</b>
          <ul class="list-disc pl-5"><li v-for="item in pending.blocks" :key="item">{{ item }}</li></ul>
        </div>
      </div>
      <template #footer>
        <PhButton variant="ghost" @click="open = false">{{ locale.cancel }}</PhButton>
        <PhButton :loading="busy" @click="confirm">{{ locale.moveConfirm.replace('{etapa}', pending?.label ?? '') }}</PhButton>
      </template>
    </PhModal>
  </PhCard>
</template>
