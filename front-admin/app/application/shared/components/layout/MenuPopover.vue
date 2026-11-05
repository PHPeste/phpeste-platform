<script setup lang="ts">
import { onBeforeUnmount, ref, useId, watch } from 'vue'

defineProps<{ label: string; align?: 'start' | 'end' }>()

const open = ref(false)
const root = ref<HTMLElement | null>(null)
const trigger = ref<HTMLButtonElement | null>(null)
const panelId = `ph-${useId()}-panel`

function close(returnFocus = true) {
  open.value = false
  if (returnFocus) trigger.value?.focus()
}

function onDocumentClick(event: MouseEvent) {
  if (root.value && !root.value.contains(event.target as Node)) close(false)
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && open.value) {
    event.stopPropagation()
    close()
  }
}

watch(open, (value) => {
  if (value) document.addEventListener('click', onDocumentClick)
  else document.removeEventListener('click', onDocumentClick)
})
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick))

defineExpose({ close })
</script>

<template>
  <div ref="root" class="relative" @keydown="onKeydown">
    <button
      ref="trigger"
      type="button"
      class="ph-topbtn"
      :aria-label="label"
      :aria-expanded="open"
      :aria-controls="panelId"
      aria-haspopup="true"
      @click="open = !open"
    >
      <slot name="trigger" :open="open" />
    </button>
    <div
      v-show="open"
      :id="panelId"
      class="absolute top-full z-40 mt-2 min-w-64 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg"
      :class="align === 'end' ? 'right-0' : 'left-0'"
    >
      <slot :close="close" />
    </div>
  </div>
</template>
