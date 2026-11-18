<script setup lang="ts">
import { ref, watch } from 'vue'
import locale from '../locales/pt-br'

const props = defineProps<{ run: (perProposal: number) => Promise<{ created: number, unfilled: number[] } | string> }>()
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ done: [created: number, unfilled: number[]] }>()

const perProposal = ref(3)
const busy = ref(false)
const error = ref<string | null>(null)

watch(open, (value) => {
  if (value) error.value = null
})

async function submit() {
  busy.value = true
  const result = await props.run(Number(perProposal.value))
  busy.value = false
  if (typeof result === 'string') {
    error.value = result
    return
  }
  emit('done', result.created, result.unfilled)
  open.value = false
}
</script>

<template>
  <PhModal v-model="open" :title="locale.autoTitle">
    <form id="auto-assign-form" class="flex flex-col gap-4" @submit.prevent="submit">
      <p class="text-sm text-(--ph-ink-2)">{{ locale.autoText }}</p>
      <PhInput v-model.number="perProposal" type="number" :label="locale.perProposal" />
      <p v-if="error" role="alert" class="alert alert-error alert-soft text-sm">{{ error }}</p>
    </form>
    <template #footer>
      <PhButton variant="ghost" @click="open = false">{{ locale.cancel }}</PhButton>
      <PhButton type="submit" form="auto-assign-form" :loading="busy">{{ locale.autoRun }}</PhButton>
    </template>
  </PhModal>
</template>
