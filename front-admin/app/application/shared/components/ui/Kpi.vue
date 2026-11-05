<script setup lang="ts">
defineProps<{ label: string, value: string | number, hint?: string }>()
</script>

<template>
  <div class="card border border-base-300 bg-base-100 px-6 py-5">
    <dl class="flex flex-col gap-1">
      <dt class="text-xs text-(--ph-ink-3)">{{ label }}</dt>
      <dd class="text-3xl leading-tight font-semibold tabular-nums">{{ value }}</dd>
      <dd v-if="hint" class="text-xs text-(--ph-ink-3)">{{ hint }}</dd>
    </dl>
  </div>
</template>
