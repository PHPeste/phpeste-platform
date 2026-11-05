<script setup lang="ts">
defineProps<{ title?: string }>()
</script>

<template>
  <section class="card border border-base-300 bg-base-100">
    <div class="card-body gap-4 p-6">
      <header v-if="title || $slots.actions" class="flex flex-wrap items-center justify-between gap-3">
        <h2 v-if="title" class="text-lg font-semibold">{{ title }}</h2>
        <div v-if="$slots.actions" class="flex items-center gap-2">
          <slot name="actions" />
        </div>
      </header>
      <slot />
    </div>
  </section>
</template>
