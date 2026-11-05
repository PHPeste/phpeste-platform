<script setup lang="ts">
import { computed, nextTick, ref, useId } from 'vue'

export interface TabItem {
  id: string
  label: string
}

const props = defineProps<{ items: TabItem[], label: string }>()
const model = defineModel<string>({ required: true })

const uid = useId()
const tabRefs = ref<HTMLButtonElement[]>([])
const tabId = (id: string) => `ph-${uid}-tab-${id}`
const panelId = (id: string) => `ph-${uid}-panel-${id}`
const activeIndex = computed(() => Math.max(0, props.items.findIndex((i) => i.id === model.value)))

async function move(index: number) {
  const next = props.items[(index + props.items.length) % props.items.length]
  if (!next) return
  model.value = next.id
  await nextTick()
  tabRefs.value.find((el) => el?.id === tabId(next.id))?.focus()
}

function onKeydown(event: KeyboardEvent) {
  const keys: Record<string, number> = {
    ArrowRight: activeIndex.value + 1,
    ArrowLeft: activeIndex.value - 1,
    Home: 0,
    End: props.items.length - 1,
  }
  const target = keys[event.key]
  if (target === undefined) return
  event.preventDefault()
  move(target)
}
</script>

<template>
  <div>
    <div
      role="tablist"
      :aria-label="label"
      class="flex gap-1 overflow-x-auto border-b border-base-300"
      @keydown="onKeydown"
    >
      <button
        v-for="item in items"
        :id="tabId(item.id)"
        :key="item.id"
        ref="tabRefs"
        type="button"
        role="tab"
        :aria-selected="item.id === model"
        :aria-controls="panelId(item.id)"
        :tabindex="item.id === model ? 0 : -1"
        class="-mb-px min-h-11 whitespace-nowrap border-b-[3px] px-4 text-sm font-medium"
        :class="
          item.id === model
            ? 'border-primary text-base-content'
            : 'border-transparent text-(--ph-ink-2) hover:text-base-content'
        "
        @click="model = item.id"
      >
        {{ item.label }}
      </button>
    </div>
    <div
      :id="panelId(model)"
      role="tabpanel"
      :aria-labelledby="tabId(model)"
      tabindex="0"
      class="pt-5"
    >
      <slot :active="model" />
    </div>
  </div>
</template>
