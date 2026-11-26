import { beforeEach, describe, expect, it, vi } from 'vitest'
import tasksService from '../services/tasksService'
import { TasksError } from '../services/tasksError'
import { useTaskBoard } from './useTaskBoard'
import { boardPayload, taskPayload } from './fixtures'

vi.mock('../services/tasksService', () => ({
  default: { board: vi.fn(), create: vi.fn(), update: vi.fn(), move: vi.fn(), remove: vi.fn(), linkOptions: vi.fn(), team: vi.fn() },
}))

const titles = (board: ReturnType<typeof useTaskBoard>) => Object.fromEntries(board.columns.value.map((column) => [column.name, column.tasks.map((task) => task.title)]))

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(tasksService.board).mockResolvedValue(boardPayload() as never)
  vi.mocked(tasksService.team).mockResolvedValue({ data: { members: [{ user_id: 'u-ana', name: 'Ana Lima', email: 'ana@x.dev' }] } } as never)
  vi.mocked(tasksService.linkOptions).mockResolvedValue({ data: [{ type: 'supplier', label: 'Fornecedor', options: [{ id: 'l-1', name: 'Buffet' }] }] } as never)
})

describe('useTaskBoard', () => {
  it('carrega as quatro colunas e diz quem pode mover', async () => {
    const board = useTaskBoard('ed-1', 'u-bia')
    await board.load()

    expect(board.state.value).toBe('ready')
    expect(Object.keys(titles(board))).toEqual(['A fazer', 'Em andamento', 'Em revisão', 'Feito'])
    expect(board.totals.value.overdue).toBe(1)
    expect(board.canMove(board.columns.value[0]!.tasks[0]!)).toBe(true)

    vi.mocked(tasksService.board).mockResolvedValue(boardPayload(false) as never)
    await board.load()
    expect(board.canMove(board.columns.value[0]!.tasks[0]!)).toBe(false)
    expect(board.canMove(board.columns.value[0]!.tasks[1]!)).toBe(true)
  })

  it('move na hora, antes da resposta, e aplica a ordem que o backend gravou', async () => {
    let resolve: (value: unknown) => void = () => {}
    vi.mocked(tasksService.move).mockReturnValue(new Promise((done) => { resolve = done }) as never)
    const board = useTaskBoard('ed-1', null)
    await board.load()

    const pending = board.move('t-1', 'c-done', 0)
    expect(titles(board)['Feito']).toEqual(['Fechar contrato do buffet'])
    expect(board.columns.value[3]!.tasks[0]!.done).toBe(true)
    expect(tasksService.move).toHaveBeenCalledWith('ed-1', 't-1', 'c-done', 0)

    resolve({ data: { task: taskPayload('t-1', 'c-done', 'Fechar contrato do buffet', { done: true }), columns: [{ id: 'c-todo', task_ids: ['t-2'] }, { id: 'c-done', task_ids: ['t-1'] }] } })
    expect(await pending).toEqual({ ok: true })
    expect(titles(board)['A fazer']).toEqual(['Pedir arte dos crachás'])
  })

  it('se a API recusa, o quadro volta ao que era', async () => {
    vi.mocked(tasksService.move).mockRejectedValue(new TasksError('x', 422, { task: ['A tarefa mudou enquanto isso. Atualize o quadro e tente de novo.'] }))
    const board = useTaskBoard('ed-1', null)
    await board.load()
    const before = titles(board)

    const result = await board.move('t-2', 'c-todo', 0)

    expect(result).toMatchObject({ ok: false, message: 'A tarefa mudou enquanto isso. Atualize o quadro e tente de novo.' })
    expect(titles(board)).toEqual(before)
  })

  it('com filtro, mover manda para o fim da coluna', async () => {
    vi.mocked(tasksService.move).mockResolvedValue({ data: { task: taskPayload('t-1', 'c-doing', 'Fechar contrato do buffet'), columns: [] } } as never)
    const board = useTaskBoard('ed-1', null)
    await board.filterBy('mine')

    await board.move('t-1', 'c-doing', 0)
    expect(tasksService.move).toHaveBeenCalledWith('ed-1', 't-1', 'c-doing', 100_000)
    expect(tasksService.board).toHaveBeenCalledWith('ed-1', 'mine')
  })

  it('salva com o vínculo separado em tipo e id e o responsável nulo quando vazio', async () => {
    vi.mocked(tasksService.create).mockResolvedValue({ data: {} } as never)
    const board = useTaskBoard('ed-1', null)

    await board.save(null, { title: ' Pedir orçamento ', description: '', assigneeId: '', dueOn: '', link: 'supplier:l-1', columnId: 'c-todo' })
    expect(tasksService.create).toHaveBeenCalledWith('ed-1', { title: 'Pedir orçamento', description: null, assignee_id: null, due_on: null, link_type: 'supplier', link_id: 'l-1', task_column_id: 'c-todo' })
  })
})
