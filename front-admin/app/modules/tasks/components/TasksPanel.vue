<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue'
import { useToast } from '@/application/shared/composables/useToast'
import { useAuthStore } from '@/stores/authStore'
import TaskCard from './TaskCard.vue'
import TaskModal from './TaskModal.vue'
import { useTaskBoard } from '../composables/useTaskBoard'
import locale from '../locales/pt-br'
import type { Filter, Task } from '../types'

const props = defineProps<{ editionId: string, readOnly: boolean }>()

const auth = useAuthStore()
const board = useTaskBoard(props.editionId, auth.user?.id ?? null)
const { columns, canManage, filter, totals, members, linkGroups, state, canReorder } = board
const toast = useToast()

const announcement = ref('')
const dragging = ref<Task | null>(null)
const over = ref<string | null>(null)
const modalOpen = ref(false)
const editing = ref<Task | null>(null)
const newColumnId = ref('')
const filters = Object.entries(locale.filters).map(([value, label]) => ({ value: value as Filter, label }))

const columnName = (columnId: string) => columns.value.find((column) => column.id === columnId)?.name ?? ''
const movable = (task: Task) => !props.readOnly && board.canMove(task)

function say(text: string) {
  announcement.value = ''
  nextTick(() => { announcement.value = text })
}

async function moveTo(task: Task, columnId: string, index: number, focus: boolean) {
  const from = columnName(task.columnId)
  const result = await board.move(task.id, columnId, index)
  if (result.ok) {
    const column = columns.value.find((item) => item.id === columnId)
    const position = (column?.tasks.findIndex((item) => item.id === task.id) ?? 0) + 1
    say(locale.moved.replace('{titulo}', task.title).replace('{coluna}', column?.name ?? '').replace('{posicao}', String(position)).replace('{total}', String(column?.tasks.length ?? 0)))
  } else {
    const text = locale.moveFailed.replace('{titulo}', task.title).replace('{motivo}', result.message).replace('{coluna}', from)
    say(text)
    toast.error(result.message)
  }
  if (focus) {
    await nextTick()
    document.getElementById(`task-${task.id}`)?.focus()
  }
}

function keymove(task: Task, event: KeyboardEvent) {
  if (!event.altKey || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return
  event.preventDefault()
  if (!movable(task)) {
    say(locale.cantMove)
    return
  }
  const columnIndex = columns.value.findIndex((column) => column.id === task.columnId)
  const column = columns.value[columnIndex]!
  const index = column.tasks.findIndex((item) => item.id === task.id)

  if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
    const target = columns.value[columnIndex + (event.key === 'ArrowLeft' ? -1 : 1)]
    if (!target) {
      say(locale.edgeColumn)
      return
    }
    moveTo(task, target.id, canReorder.value ? Math.min(index, target.tasks.length) : target.tasks.length, true)
    return
  }
  if (!canReorder.value) {
    say(locale.keyboardHintFiltered)
    return
  }
  const next = index + (event.key === 'ArrowUp' ? -1 : 1)
  if (next < 0 || next >= column.tasks.length) {
    say(locale.edgePosition)
    return
  }
  moveTo(task, column.id, next, true)
}

function start(event: DragEvent, task: Task) {
  dragging.value = task
  event.dataTransfer?.setData('text/plain', task.id)
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
}

function drop(columnId: string, beforeTaskId: string | null) {
  const task = dragging.value
  dragging.value = null
  over.value = null
  if (!task) return
  const column = columns.value.find((item) => item.id === columnId)!
  const rest = column.tasks.filter((item) => item.id !== task.id)
  const index = beforeTaskId === null || !canReorder.value ? rest.length : Math.max(0, rest.findIndex((item) => item.id === beforeTaskId))
  const current = column.tasks.findIndex((item) => item.id === task.id)
  if (task.columnId === columnId && (current === index || !canReorder.value)) return
  moveTo(task, columnId, index, false)
}

function openNew(columnId: string) {
  editing.value = null
  newColumnId.value = columnId
  modalOpen.value = true
}

function openEdit(task: Task) {
  editing.value = task
  modalOpen.value = true
}

onMounted(() => board.load())
</script>

<template>
  <section class="flex flex-col gap-6">
    <header class="flex flex-wrap items-end justify-between gap-3">
      <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl leading-tight font-semibold">{{ locale.title }}</h1>
        <p class="text-(--ph-ink-2)">{{ locale.subtitle }}</p>
      </div>
      <PhButton v-if="canManage && !readOnly && columns.length" @click="openNew(columns[0]!.id)">{{ locale.newTask }}</PhButton>
    </header>

    <p v-if="readOnly" role="status" class="alert alert-info alert-soft text-sm">{{ locale.readOnly }}</p>

    <div class="flex flex-wrap items-center gap-2.5" role="group" :aria-label="locale.filterLabel">
      <PhChip v-for="item in filters" :key="item.value" :model-value="filter === item.value" @update:model-value="board.filterBy(item.value)">{{ item.label }}</PhChip>
      <span v-if="totals.overdue" class="text-sm font-medium text-(--ph-danger)">{{ totals.overdue === 1 ? locale.overdueOne : locale.overdueCount.replace('{n}', String(totals.overdue)) }}</span>
    </div>

    <p id="tasks-keyboard-hint" class="text-xs text-(--ph-ink-3)">{{ canReorder ? locale.keyboardHint : locale.keyboardHintFiltered }}</p>
    <p class="sr-only" role="status" aria-live="assertive" data-testid="tasks-announcer">{{ announcement }}</p>

    <PhPageSkeleton v-if="state === 'loading'" :cards="4" :lines="4" />
    <PhLoadError v-else-if="state === 'error'" :title="locale.loadError" @retry="board.load" />

    <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" data-testid="task-board">
      <section
        v-for="column in columns"
        :key="column.id"
        class="flex min-h-48 flex-col gap-3 rounded-(--radius-box) border border-(--ph-line) bg-(--ph-surface-2) p-3 transition-colors"
        :class="{ 'border-(--ph-brand)': over === column.id }"
        :aria-labelledby="`column-${column.id}`"
        :data-column="column.name"
        @dragover.prevent="over = column.id"
        @dragleave="over === column.id && (over = null)"
        @drop.prevent="drop(column.id, null)"
      >
        <header class="flex items-baseline justify-between gap-2 px-1">
          <h2 :id="`column-${column.id}`" class="font-semibold">{{ column.name }}</h2>
          <span class="text-xs text-(--ph-ink-3) tabular-nums" :aria-label="column.tasks.length === 1 ? locale.columnCountOne : locale.columnCount.replace('{n}', String(column.tasks.length))">{{ column.tasks.length }}</span>
        </header>
        <ul class="flex flex-col gap-2.5">
          <TaskCard
            v-for="task in column.tasks"
            :key="task.id"
            :task="task"
            :column-name="column.name"
            :draggable="movable(task)"
            :editable="canManage && !readOnly"
            :dragging="dragging?.id === task.id"
            @edit="openEdit(task)"
            @keymove="keymove(task, $event)"
            @dragstart="start($event, task)"
            @dragend="dragging = null; over = null"
            @drop.prevent.stop="drop(column.id, task.id)"
            @dragover.prevent
          />
        </ul>
        <p v-if="!column.tasks.length" class="px-1 text-xs text-(--ph-ink-3)">{{ filter === 'all' ? locale.emptyColumn : locale.emptyFiltered }}</p>
        <button v-if="canManage && !readOnly" type="button" class="btn btn-ghost btn-sm mt-auto justify-start" :aria-label="locale.newTaskIn.replace('{coluna}', column.name)" @click="openNew(column.id)">+ {{ locale.newTask }}</button>
      </section>
    </div>

    <TaskModal
      v-model="modalOpen"
      :task="editing"
      :column-id="newColumnId"
      :columns="columns"
      :members="members"
      :link-groups="linkGroups"
      :save="board.save"
      :remove="board.remove"
      @saved="toast.success"
    />
  </section>
</template>
