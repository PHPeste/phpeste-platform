<script setup lang="ts">
defineProps<{ caption: string }>()
</script>

<template>
  <!-- A região rolável precisa receber foco para quem usa só teclado -->
  <div class="overflow-x-auto" role="region" :aria-label="caption" tabindex="0">
    <table class="table">
      <caption class="sr-only">{{ caption }}</caption>
      <slot />
    </table>
  </div>
</template>
