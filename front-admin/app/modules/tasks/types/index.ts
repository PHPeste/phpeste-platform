import type { FilterValue } from '../services/tasksService'

export type Filter = FilterValue

export interface Task {
  id: string
  columnId: string
  title: string
  description: string | null
  assigneeId: string | null
  assigneeName: string | null
  dueOn: string | null
  overdue: boolean
  done: boolean
  link: { type: string, typeLabel: string, id: string, name: string } | null
}

export interface Column {
  id: string
  name: string
  isDone: boolean
  tasks: Task[]
}

export interface Member { id: string, name: string }

export interface LinkGroup { type: string, label: string, options: Array<{ id: string, name: string }> }

export interface TaskInput {
  title: string
  description: string
  assigneeId: string
  dueOn: string
  link: string
  columnId: string
}

export type Failure = { ok: false, message: string, fieldErrors: Record<string, string> }

export type MutationResult = { ok: true } | Failure
