import { computed, ref } from 'vue'
import tasksService, { type TaskPayload } from '../services/tasksService'
import { TasksError } from '../services/tasksError'
import locale from '../locales/pt-br'
import type { Column, Failure, Filter, LinkGroup, Member, MutationResult, Task, TaskInput } from '../types'

export const translateTask = (payload: TaskPayload): Task => ({
  id: payload.id,
  columnId: payload.column_id,
  title: payload.title,
  description: payload.description,
  assigneeId: payload.assignee?.id ?? null,
  assigneeName: payload.assignee?.name ?? null,
  dueOn: payload.due_on,
  overdue: payload.overdue,
  done: payload.done,
  link: payload.link ? { type: payload.link.type, typeLabel: payload.link.type_label, id: payload.link.id, name: payload.link.name } : null,
})

const FIELDS: Record<string, string> = { title: 'title', description: 'description', assignee_id: 'assigneeId', due_on: 'dueOn', link_type: 'link', link_id: 'link', task_column_id: 'columnId', task: 'task' }

function failure(error: unknown): Failure {
  if (error instanceof TasksError && error.statusCode === 422 && error.fieldErrors) {
    const fieldErrors = Object.fromEntries(Object.entries(error.fieldErrors).map(([key, messages]) => [FIELDS[key] ?? key, messages[0] ?? '']))
    return { ok: false, message: Object.values(fieldErrors)[0] ?? locale.genericError, fieldErrors }
  }
  if (error instanceof TasksError && error.statusCode === 403) return { ok: false, message: locale.forbidden, fieldErrors: {} }
  return { ok: false, message: locale.genericError, fieldErrors: {} }
}

const blank = (text: string) => (text.trim() === '' ? null : text.trim())

/** Fim da coluna: o backend encaixa qualquer posição maior no último lugar. */
const END = 100_000

export function useTaskBoard(editionId: string, meId: string | null) {
  const columns = ref<Column[]>([])
  const canManage = ref(false)
  const filter = ref<Filter>('all')
  const totals = ref({ tasks: 0, overdue: 0 })
  const members = ref<Member[]>([])
  const linkGroups = ref<LinkGroup[]>([])
  const state = ref<'loading' | 'ready' | 'error'>('loading')
  /** Com filtro a coluna mostra só parte das tarefas: a ordem dentro dela não vale, então só muda de coluna. */
  const canReorder = computed(() => filter.value === 'all')

  async function refresh() {
    const board = (await tasksService.board(editionId, filter.value)).data
    canManage.value = board.can_manage
    totals.value = board.totals
    columns.value = board.columns.map((column) => ({ id: column.id, name: column.name, isDone: column.is_done, tasks: column.tasks.map(translateTask) }))
  }

  async function load() {
    state.value = 'loading'
    try {
      await refresh()
      state.value = 'ready'
    } catch {
      state.value = 'error'
      return
    }
    if (!canManage.value) return
    Promise.all([tasksService.team(editionId), tasksService.linkOptions(editionId)])
      .then(([team, links]) => {
        members.value = team.data.members.map((member) => ({ id: member.user_id, name: member.name ?? member.email ?? '' }))
        linkGroups.value = links.data.map((group) => ({ type: group.type, label: group.label, options: group.options }))
      })
      .catch(() => {})
  }

  async function filterBy(next: Filter) {
    filter.value = next
    await load()
  }

  const canMove = (task: Task) => canManage.value || (meId !== null && task.assigneeId === meId)

  function find(taskId: string) {
    for (const column of columns.value) {
      const index = column.tasks.findIndex((task) => task.id === taskId)
      if (index >= 0) return { column, index, task: column.tasks[index]! }
    }
    return null
  }

  /**
   * Move na hora e chama a API. Com a resposta, aplica a ordem que o backend
   * gravou nas colunas afetadas; com erro, o quadro volta ao que era.
   */
  async function move(taskId: string, toColumnId: string, toIndex: number): Promise<MutationResult> {
    const found = find(taskId)
    const target = columns.value.find((column) => column.id === toColumnId)
    if (!found || !target) return { ok: false, message: locale.genericError, fieldErrors: {} }

    const before = columns.value.map((column) => ({ ...column, tasks: [...column.tasks] }))
    const index = Math.max(0, Math.min(toIndex, target.tasks.filter((task) => task.id !== taskId).length))
    columns.value = columns.value.map((column) => {
      const tasks = column.tasks.filter((task) => task.id !== taskId)
      if (column.id === toColumnId) tasks.splice(index, 0, { ...found.task, columnId: toColumnId, done: column.isDone, overdue: column.isDone ? false : found.task.overdue })
      return { ...column, tasks }
    })

    try {
      const response = await tasksService.move(editionId, taskId, toColumnId, canReorder.value ? index : END)
      const moved = translateTask(response.data.task)
      const orders = new Map(response.data.columns.map((column) => [column.id, column.task_ids]))
      columns.value = columns.value.map((column) => {
        const order = orders.get(column.id)
        const tasks = column.tasks.map((task) => (task.id === moved.id ? moved : task))
        if (!order) return { ...column, tasks }
        const rank = new Map(order.map((id, position) => [id, position]))
        return { ...column, tasks: [...tasks].sort((a, b) => (rank.get(a.id) ?? 0) - (rank.get(b.id) ?? 0)) }
      })
      return { ok: true }
    } catch (error) {
      columns.value = before
      return failure(error)
    }
  }

  async function mutate(request: () => Promise<unknown>): Promise<MutationResult> {
    try {
      await request()
      await refresh()
      return { ok: true }
    } catch (error) {
      return failure(error)
    }
  }

  const linkBody = (link: string) => {
    const [type, id] = link.split(':')
    return type && id ? { link_type: type, link_id: id } : { link_type: null, link_id: null }
  }

  const save = (taskId: string | null, input: TaskInput) => mutate(() => {
    const body = {
      title: input.title.trim(),
      description: blank(input.description),
      assignee_id: input.assigneeId || null,
      due_on: input.dueOn || null,
      ...linkBody(input.link),
    }
    return taskId ? tasksService.update(editionId, taskId, body) : tasksService.create(editionId, { ...body, task_column_id: input.columnId })
  })

  const remove = (taskId: string) => mutate(() => tasksService.remove(editionId, taskId))

  return { columns, canManage, filter, totals, members, linkGroups, state, canReorder, load, filterBy, canMove, move, save, remove }
}
