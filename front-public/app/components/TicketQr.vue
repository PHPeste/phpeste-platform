<script setup lang="ts">
import { computed } from 'vue'
import { qrPath } from '@/application/shared/qr'

const props = withDefaults(defineProps<{ value: string, label: string, size?: string }>(), { size: 'size-48' })
const qr = computed(() => qrPath(props.value))
</script>

<template>
  <svg :viewBox="`0 0 ${qr.size} ${qr.size}`" role="img" :aria-label="label" class="rounded-box bg-white" :class="size" shape-rendering="crispEdges" data-testid="ticket-qr">
    <path :d="qr.path" fill="#000" />
  </svg>
</template>
