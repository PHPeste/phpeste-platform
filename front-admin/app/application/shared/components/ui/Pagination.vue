<script setup lang="ts">
import { computed } from 'vue'
import locale from '@/application/locales/pt-br/ui'

const props = defineProps<{ pageCount: number }>()
const page = defineModel<number>('page', { required: true })

const pages = computed(() => {
  const total = props.pageCount
  const start = Math.max(1, Math.min(page.value - 2, total - 4))
  const end = Math.min(total, start + 4)
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})

function go(n: number) {
  page.value = Math.min(Math.max(1, n), props.pageCount)
}
</script>

<template>
  <nav :aria-label="locale.pagination" class="flex items-center gap-1">
    <button
      type="button"
      class="btn btn-ghost btn-sm min-h-11"
      :disabled="page <= 1"
      @click="go(page - 1)"
    >
      <span aria-hidden="true">←</span>
      <span class="sr-only">{{ locale.previousPage }}</span>
    </button>
    <button
      v-for="n in pages"
      :key="n"
      type="button"
      class="btn btn-sm min-h-11 min-w-11"
      :class="n === page ? 'btn-primary' : 'btn-ghost'"
      :aria-current="n === page ? 'page' : undefined"
      :aria-label="locale.page(n)"
      @click="go(n)"
    >
      {{ n }}
    </button>
    <button
      type="button"
      class="btn btn-ghost btn-sm min-h-11"
      :disabled="page >= pageCount"
      @click="go(page + 1)"
    >
      <span aria-hidden="true">→</span>
      <span class="sr-only">{{ locale.nextPage }}</span>
    </button>
  </nav>
</template>
