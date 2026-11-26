import { describe, expect, it, vi } from 'vitest'
import { httpClient, HttpClientError } from '@/infrastructure/http/HttpClient'
import tasksService from './tasksService'
import { TasksError } from './tasksError'

vi.mock('@/infrastructure/http/HttpClient', () => ({
  httpClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  HttpClientError: class extends Error {
    statusCode: number | null
    fieldErrors: Record<string, string[]> | null

    constructor(message: string, statusCode: number | null = null, fieldErrors: Record<string, string[]> | null = null) {
      super(message)
      this.statusCode = statusCode
      this.fieldErrors = fieldErrors
    }
  },
}))

describe('tasksService', () => {
  it('chama as rotas do quadro, sem filtro quando é "todas"', async () => {
    vi.mocked(httpClient.get).mockResolvedValue({ data: {} })
    vi.mocked(httpClient.post).mockResolvedValue({ data: {} })

    await tasksService.board('ed-1', 'all')
    await tasksService.board('ed-1', 'mine')
    await tasksService.move('ed-1', 't-1', 'c-2', 3)
    await tasksService.create('ed-1', { title: 'Reservar o espaço', task_column_id: 'c-1' })

    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/tasks/board', { query: {} })
    expect(httpClient.get).toHaveBeenCalledWith('/editions/ed-1/tasks/board', { query: { filter: 'mine' } })
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/tasks/t-1/move', { body: { task_column_id: 'c-2', position: 3 } })
    expect(httpClient.post).toHaveBeenCalledWith('/editions/ed-1/tasks', { body: { title: 'Reservar o espaço', task_column_id: 'c-1' } })
  })

  it('troca o erro HTTP pelo erro do módulo', async () => {
    vi.mocked(httpClient.post).mockRejectedValue(new HttpClientError('x', 403))
    await expect(tasksService.move('ed-1', 't-1', 'c-2', 0)).rejects.toMatchObject({ name: 'TasksError', statusCode: 403 })
    await expect(tasksService.move('ed-1', 't-1', 'c-2', 0)).rejects.toBeInstanceOf(TasksError)
  })
})
