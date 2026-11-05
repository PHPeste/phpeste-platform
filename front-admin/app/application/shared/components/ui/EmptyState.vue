<script setup lang="ts">
defineProps<{ title: string, description?: string }>()
</script>

<template>
  <div class="flex flex-col items-center gap-3 px-4 py-10 text-center">
    <div
      v-if="$slots.icon"
      class="flex size-14 items-center justify-center rounded-full bg-(--ph-surface-2) text-primary"
    >
      <slot name="icon" />
    </div>
    <h3 class="text-base font-semibold">{{ title }}</h3>
    <p v-if="description" class="max-w-sm text-sm text-(--ph-ink-2)">{{ description }}</p>
    <div v-if="$slots.default" class="mt-1">
      <slot />
    </div>
  </div>
</template>
