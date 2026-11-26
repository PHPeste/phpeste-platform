<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import locale from '../locales/pt-br'

const props = defineProps<{ errors: Record<string, string>, labels: Record<string, string>, fieldId: (path: string) => string }>()
const root = ref<HTMLElement | null>(null)
const entries = computed(() => Object.entries(props.errors).filter(([, message]) => !!message))
const title = computed(() => (entries.value.length === 1 ? locale.errorsTitleOne : locale.errorsTitle.replace('{n}', String(entries.value.length))))

watch(() => props.errors, async () => {
  if (!entries.value.length) return
  await nextTick()
  root.value?.focus()
})

function focus(path: string) {
  document.getElementById(props.fieldId(path))?.focus()
}
</script>

<template>
  <div v-if="entries.length" ref="root" role="alert" tabindex="-1" class="alert alert-error alert-soft flex flex-col items-start gap-1.5 outline-none" data-testid="checkout-errors">
    <b class="font-semibold">{{ title }}</b>
    <ul class="list-disc pl-5 text-sm">
      <li v-for="[path, message] in entries" :key="path">
        <a :href="`#${fieldId(path)}`" class="link" @click.prevent="focus(path)">{{ labels[path] ?? path }}</a>: {{ message }}
      </li>
    </ul>
  </div>
</template>
