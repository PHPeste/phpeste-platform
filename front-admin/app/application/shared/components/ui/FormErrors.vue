<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import locale from '@/application/locales/pt-br/ui'

const props = defineProps<{ errors: Record<string, string | null | undefined>, labels: Record<string, string>, fieldIds?: Record<string, string> }>()

const root = ref<HTMLElement | null>(null)
const entries = computed(() => Object.entries(props.errors).filter((entry): entry is [string, string] => !!entry[1]))
const title = computed(() => (entries.value.length === 1 ? locale.formErrorsOne : locale.formErrorsMany(entries.value.length)))

watch(() => entries.value.length, async (count, previous) => {
  if (count > 0 && count !== previous) {
    await nextTick()
    root.value?.focus()
  }
})

function focusField(field: string) {
  const id = props.fieldIds?.[field]
  if (id) document.getElementById(id)?.focus()
}
</script>

<template>
  <div v-if="entries.length" ref="root" role="alert" tabindex="-1" class="alert alert-error alert-soft flex flex-col items-start gap-1.5 outline-none" data-testid="form-errors">
    <b class="font-semibold">{{ title }}</b>
    <span class="text-sm">{{ locale.formErrorsHint }}</span>
    <ul class="list-disc pl-5 text-sm">
      <li v-for="[field, message] in entries" :key="field">
        <a v-if="fieldIds?.[field]" :href="`#${fieldIds[field]}`" class="link" @click.prevent="focusField(field)">{{ labels[field] ?? field }}</a>
        <b v-else class="font-medium">{{ labels[field] ?? field }}</b>: {{ message }}
      </li>
    </ul>
  </div>
</template>
