<script setup lang="ts">
import { computed } from 'vue'

export type BadgeStatus = 'ok' | 'warn' | 'danger' | 'info' | 'neutral'

const props = withDefaults(defineProps<{ status?: BadgeStatus, dot?: boolean }>(), {
  status: 'neutral',
  dot: true,
})

// Classes completas (não montadas por string) para o Tailwind enxergá-las.
const tone = computed(
  () =>
    ({
      ok: 'bg-(--ph-ok-bg) text-(--ph-ok)',
      warn: 'bg-(--ph-warn-bg) text-(--ph-warn)',
      danger: 'bg-(--ph-danger-bg) text-(--ph-danger)',
      info: 'bg-(--ph-info-bg) text-(--ph-info)',
      neutral: 'bg-(--ph-surface-2) text-(--ph-ink-2)',
    })[props.status],
)
</script>

<template>
  <span
    class="inline-flex h-6.5 items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 text-xs font-medium"
    :class="tone"
    :data-status="status"
  >
    <span v-if="dot" class="size-1.5 rounded-full bg-current" aria-hidden="true" />
    <slot />
  </span>
</template>
