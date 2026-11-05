<script setup lang="ts">
import { Check, ChevronDown } from '@lucide/vue'
import { useEdition } from '@/application/shared/composables/useEdition'
import locale from '@/modules/shell/locales/pt-br'

const { editions, current, status, select, load } = useEdition()

async function choose(id: number, close: () => void) {
  close()
  await select(id)
}
</script>

<template>
  <MenuPopover :label="locale.switchEdition" align="start">
    <template #trigger="{ open }">
      <span class="flex size-7 items-center justify-center rounded-lg bg-(--ph-brand) text-xs font-semibold text-(--ph-on-brand)" aria-hidden="true">{{ current?.yearBadge ?? '–' }}</span>
      <span class="flex flex-col text-left leading-tight">
        <span class="text-sm font-semibold">{{ current?.name ?? locale.noEdition }}</span>
        <span class="text-xs font-normal text-(--ph-ink-3)">{{ current ? `${current.city} · ${current.state}` : '' }}</span>
      </span>
      <ChevronDown class="size-4 transition-transform" :class="{ 'rotate-180': open }" aria-hidden="true" />
    </template>
    <template #default="{ close }">
      <p v-if="status === 'error'" role="alert" class="p-2 text-sm">
        {{ locale.editionsError }}
        <button type="button" class="link" @click="load()">{{ locale.retry }}</button>
      </p>
      <p v-else-if="editions.length === 0" class="p-2 text-sm text-(--ph-ink-2)">{{ locale.noEditionHint }}</p>
      <ul v-else :aria-label="locale.editionList" class="flex flex-col gap-0.5">
        <li v-for="edition in editions" :key="edition.id">
          <button
            type="button"
            class="flex min-h-11 w-full items-center gap-3 rounded-lg px-3 text-left text-sm hover:bg-base-200"
            :aria-current="edition.id === current?.id ? 'true' : undefined"
            @click="choose(edition.id, close)"
          >
            <span class="flex flex-col leading-tight">
              <span class="font-medium">{{ edition.name }}</span>
              <span class="text-xs text-(--ph-ink-3)">{{ edition.city }} · {{ edition.state }}</span>
            </span>
            <Check v-if="edition.id === current?.id" class="ml-auto size-4" aria-hidden="true" />
          </button>
        </li>
      </ul>
    </template>
  </MenuPopover>
</template>
