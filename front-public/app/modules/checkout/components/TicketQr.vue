<script setup lang="ts">
import { computed } from 'vue'
import { encode } from 'uqr'

const props = defineProps<{ value: string, label: string }>()
const qr = computed(() => encode(props.value, { ecc: 'M', border: 2 }))
const path = computed(() => qr.value.data
  .flatMap((row, y) => row.map((dark, x) => (dark ? `M${x} ${y}h1v1h-1z` : '')))
  .join(''))
</script>

<template>
  <svg :viewBox="`0 0 ${qr.size} ${qr.size}`" role="img" :aria-label="label" class="size-48 rounded-box bg-white" shape-rendering="crispEdges" data-testid="ticket-qr">
    <path :d="path" fill="#000" />
  </svg>
</template>
