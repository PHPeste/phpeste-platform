import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import { TasksError } from './tasksError'

export type FilterValue = 'all' | 'mine' | 'overdue'

export interface TaskPayload {
  id: string
  column_id: string
  position: number
  title: string
  description: string | null
  assignee: { id: string, name: string | null } | null
  due_on: string | null
  overdue: boolean
  done: boolean
  link: { type: string, type_label: string, id: string, name: string } | null
  updated_at: string
}

export interface BoardPayload {
  filter: FilterValue
  can_manage: boolean
  columns: Array<{ id: string, name: string, position: number, is_done: boolean, count: number, tasks: TaskPayload[] }>
  totals: { tasks: number, overdue: number }
}

export interface MovePayload { task: TaskPayload, columns: Array<{ id: string, task_ids: string[] }> }

export interface LinkOptionsPayload { type: string, label: string, options: Array<{ id: string, name: string }> }

export interface MemberPayload { user_id: string, name: string | null, email: string | null }

export interface TaskBody {
  title?: string
  description?: string | null
  assignee_id?: string | null
  due_on?: string | null
  link_type?: string | null
  link_id?: string | null
  task_column_id?: string
}

const base = (editionId: string) => `/editions/${editionId}/tasks`

async function call<T>(request: () => Promise<T>): Promise<T> {
  try {
    return await request()
  } catch (error) {
    if (error instanceof HttpClientError) {
      throw new TasksError(error.message, error.statusCode, error.fieldErrors)
    }
    throw new TasksError('Request failed')
  }
}

export default {
  board: (editionId: string, filter: FilterValue) => call(() => httpClient.get<{ data: BoardPayload }>(`${base(editionId)}/board`, { query: filter === 'all' ? {} : { filter } })),
  create: (editionId: string, body: TaskBody) => call(() => httpClient.post<{ data: TaskPayload }>(base(editionId), { body })),
  update: (editionId: string, taskId: string, body: TaskBody) => call(() => httpClient.patch<{ data: TaskPayload }>(`${base(editionId)}/${taskId}`, { body })),
  move: (editionId: string, taskId: string, columnId: string, position: number) =>
    call(() => httpClient.post<{ data: MovePayload }>(`${base(editionId)}/${taskId}/move`, { body: { task_column_id: columnId, position } })),
  remove: (editionId: string, taskId: string) => call(() => httpClient.delete<null>(`${base(editionId)}/${taskId}`)),
  linkOptions: (editionId: string) => call(() => httpClient.get<{ data: LinkOptionsPayload[] }>(`${base(editionId)}/link-options`)),
  team: (editionId: string) => call(() => httpClient.get<{ data: { members: MemberPayload[] } }>(`/editions/${editionId}/team`)),
}
