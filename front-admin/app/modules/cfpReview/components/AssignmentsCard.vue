<script setup lang="ts">
import { computed, ref } from 'vue'
import locale from '../locales/pt-br'
import type { Reviewer } from '../types'

const props = defineProps<{ assignments: Array<{ id: string, name: string }>, reviewers: Reviewer[] }>()
const emit = defineEmits<{ assign: [reviewerId: string], unassign: [reviewerId: string, name: string] }>()

const choice = ref('')
const options = computed(() => props.reviewers
  .filter((reviewer) => !props.assignments.some((assignment) => assignment.id === reviewer.id))
  .map((reviewer) => ({ value: reviewer.id, label: locale.reviewerLoad.replace('{nome}', reviewer.name).replace('{n}', String(reviewer.assigned)) })))

function assign() {
  if (!choice.value) return
  emit('assign', choice.value)
  choice.value = ''
}
</script>

<template>
  <section class="flex flex-col gap-3">
    <h3 class="text-lg font-semibold">{{ locale.assignmentsTitle }}</h3>
    <p v-if="!assignments.length" class="text-sm text-(--ph-ink-2)">{{ locale.noAssignments }}</p>
    <ul v-else class="flex flex-wrap gap-2">
      <li v-for="assignment in assignments" :key="assignment.id" class="flex items-center gap-1 rounded-full border border-(--ph-line) py-1 pr-1 pl-3 text-sm">
        {{ assignment.name }}
        <button type="button" class="btn btn-ghost btn-xs btn-circle" :aria-label="locale.unassign.replace('{nome}', assignment.name)" @click="emit('unassign', assignment.id, assignment.name)">×</button>
      </li>
    </ul>
    <div v-if="options.length" class="flex flex-wrap items-end gap-2">
      <div class="min-w-56 flex-1">
        <PhSelect v-model="choice" :label="locale.addReviewer" :options="options" :placeholder="locale.chooseReviewer" />
      </div>
      <PhButton variant="secondary" :disabled="!choice" @click="assign">{{ locale.assign }}</PhButton>
    </div>
  </section>
</template>
