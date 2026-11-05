<script setup lang="ts">
const model = defineModel<boolean>({ default: false })
</script>

<template>
  <button
    type="button"
    :aria-pressed="model"
    class="inline-flex min-h-9 items-center rounded-full border px-3.5 text-sm font-medium"
    :class="
      model
        ? 'border-primary bg-primary text-primary-content'
        : 'border-base-300 bg-base-100 text-(--ph-ink-2) hover:bg-base-200'
    "
    @click="model = !model"
  >
    <slot />
  </button>
</template>
