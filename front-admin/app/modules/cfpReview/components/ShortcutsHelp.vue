<script setup lang="ts">
import locale from '../locales/pt-br'
</script>

<template>
  <details class="rounded-(--ph-radius) border border-(--ph-line) px-3.5 py-2 text-sm">
    <summary class="min-h-9 cursor-pointer py-1.5 font-medium">{{ locale.shortcutsTitle }}</summary>
    <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 pt-2 pb-1">
      <template v-for="[keys, action] in locale.shortcuts" :key="keys">
        <dt><kbd class="kbd kbd-sm">{{ keys }}</kbd></dt>
        <dd class="text-(--ph-ink-2)">{{ action }}</dd>
      </template>
    </dl>
  </details>
</template>
