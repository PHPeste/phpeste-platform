<script setup lang="ts">
import { computed } from 'vue'
import { formatShort, initials, daysLate, dueSoon } from '../composables/dates'
import locale from '../locales/pt-br'
import type { Task } from '../types'

const props = defineProps<{ task: Task, columnName: string, draggable: boolean, editable: boolean, dragging: boolean }>()
defineEmits<{ edit: [], keymove: [event: KeyboardEvent], dragstart: [event: DragEvent], dragend: [] }>()

const late = computed(() => (props.task.overdue ? daysLate(props.task.dueOn) : 0))
const dueText = computed(() => {
  if (props.task.done) return locale.doneLabel
  if (props.task.overdue) {
    if (late.value === 0) return locale.overdueNoDays
    return late.value === 1 ? locale.overdueDay : locale.overdueDays.replace('{n}', String(late.value))
  }
  return props.task.dueOn ? locale.due.replace('{data}', formatShort(props.task.dueOn)) : ''
})
const soon = computed(() => !props.task.done && !props.task.overdue && dueSoon(props.task.dueOn))
</script>

<template>
  <li
    :id="`task-${task.id}`"
    class="flex flex-col gap-2 rounded-(--radius-box) border bg-(--ph-surface) p-3 shadow-xs outline-offset-2 focus-visible:outline-2 focus-visible:outline-(--ph-brand)"
    :class="[task.overdue ? 'border-(--ph-danger)' : 'border-(--ph-line)', { 'cursor-grab': draggable, 'opacity-50': dragging }]"
    :draggable="draggable"
    :tabindex="0"
    :aria-label="locale.card.replace('{titulo}', task.title).replace('{coluna}', columnName)"
    aria-describedby="tasks-keyboard-hint"
    data-testid="task-card"
    @keydown="$emit('keymove', $event)"
    @dragstart="$emit('dragstart', $event)"
    @dragend="$emit('dragend')"
  >
    <div class="flex items-start justify-between gap-2">
      <p class="font-medium" :class="{ 'text-(--ph-ink-3) line-through': task.done }">{{ task.title }}</p>
      <button v-if="editable" type="button" class="btn btn-ghost btn-xs" :aria-label="locale.edit.replace('{titulo}', task.title)" @click="$emit('edit')">✎</button>
    </div>
    <p v-if="task.link" class="text-xs text-(--ph-ink-3)">{{ task.link.typeLabel }}: {{ task.link.name }}</p>
    <div class="flex flex-wrap items-center gap-2 text-xs">
      <span
        class="grid size-6 place-items-center rounded-full bg-(--ph-surface-2) text-[10px] font-semibold"
        :title="task.assigneeName ?? locale.noAssignee"
      >
        <span aria-hidden="true">{{ task.assigneeName ? initials(task.assigneeName) : '–' }}</span>
        <span class="sr-only">{{ task.assigneeName ? locale.assignee.replace('{nome}', task.assigneeName) : locale.noAssignee }}</span>
      </span>
      <span v-if="dueText" :class="task.overdue ? 'font-medium text-(--ph-danger)' : 'text-(--ph-ink-2)'">{{ dueText }}</span>
      <PhBadge v-if="task.overdue" status="danger" class="ml-auto">{{ locale.late }}</PhBadge>
      <PhBadge v-else-if="soon" status="warn" class="ml-auto">{{ locale.soon }}</PhBadge>
    </div>
  </li>
</template>
