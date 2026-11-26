import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page, Route } from '@playwright/test'

const session = (id: string) => ({ user: { id, name: 'Rita Organiza', email: 'rita@php-pb.net', avatarUrl: null }, token: 'e2e-token' })
const edition = (role: string) => ({
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHP-PB', slug: 'php-pb' },
  status: 'sales_open', status_label: 'Vendas abertas', starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role,
})
const task = (id: string, column: string, title: string, extra: Record<string, unknown> = {}) => ({
  id, column_id: column, position: 0, title, description: null, assignee: { id: 'u-ana', name: 'Ana Lima' }, due_on: '2099-08-15', overdue: false, done: false, link: null, updated_at: '2027-03-01T12:00:00Z', ...extra,
})
const COLUMNS = [['c-todo', 'A fazer', false], ['c-doing', 'Em andamento', false], ['c-review', 'Em revisão', false], ['c-done', 'Feito', true]] as const

interface Call { method: string, path: string, query: string, body: unknown }

async function open(page: Page, goto: (url: string, options: { waitUntil: 'hydration' }) => Promise<unknown>, role = 'organizador', handle: (route: Route, call: Call) => boolean = () => false) {
  const calls: Call[] = []
  const board: Record<string, ReturnType<typeof task>[]> = {
    'c-todo': [task('t-1', 'c-todo', 'Fechar contrato do buffet'), task('t-2', 'c-todo', 'Pedir arte dos crachás', { assignee: { id: 'u-vol', name: 'Vitor Voluntário' } })],
    'c-doing': [task('t-3', 'c-doing', 'Convidar palestrantes de AL e SE', { overdue: true, due_on: '2027-03-01' })],
    'c-review': [],
    'c-done': [],
  }
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session(role === 'organizador' ? 'u-rita' : 'u-vol'))
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition(role)] } }))
  await page.route('**/api/v1/editions/ed-27/team', (route) => route.fulfill({ status: 200, json: { data: { members: [{ user_id: 'u-ana', name: 'Ana Lima', email: 'ana@x.dev' }], invitations: [], summary: {} } } }))
  await page.route('**/api/v1/editions/ed-27/tasks/**', (route) => {
    const request = route.request()
    const url = new URL(request.url())
    const call = { method: request.method(), path: url.pathname.replace('/api/v1/editions/ed-27/tasks', ''), query: url.search, body: request.postDataJSON?.() ?? null }
    calls.push(call)
    if (handle(route, call)) return
    if (call.path === '/board') {
      return route.fulfill({ status: 200, json: { data: {
        filter: 'all', can_manage: role === 'organizador',
        columns: COLUMNS.map(([id, name, done], position) => ({ id, name, position, is_done: done, count: board[id]!.length, tasks: board[id] })),
        totals: { tasks: 3, overdue: 1 },
      } } })
    }
    if (call.path === '/link-options') return route.fulfill({ status: 200, json: { data: [] } })
    const moved = /^\/(t-\d)\/move$/.exec(call.path)
    if (moved) {
      const body = call.body as { task_column_id: string, position: number }
      const id = moved[1]!
      const from = Object.keys(board).find((key) => board[key]!.some((item) => item.id === id))!
      const item = board[from]!.find((entry) => entry.id === id)!
      board[from] = board[from]!.filter((entry) => entry.id !== id)
      const target = board[body.task_column_id]!
      target.splice(Math.min(body.position, target.length), 0, { ...item, column_id: body.task_column_id })
      return route.fulfill({ status: 200, json: { data: { task: { ...item, column_id: body.task_column_id }, columns: [from, body.task_column_id].map((key) => ({ id: key, task_ids: board[key]!.map((entry) => entry.id) })) } } })
    }
    return route.fulfill({ status: 200, json: { data: {} } })
  })
  await goto('/tarefas', { waitUntil: 'hydration' })
  return calls
}

const column = (page: Page, name: string) => page.locator(`[data-column="${name}"]`)

test.describe('Tarefas', () => {
  test('quatro colunas com contagem, atrasada em destaque e filtros', async ({ page, goto }) => {
    const calls = await open(page, goto)
    await expect(page.getByRole('heading', { name: 'Tarefas', level: 1 })).toBeVisible()
    await expect(page.locator('[data-column]')).toHaveCount(4)
    await expect(column(page, 'A fazer').getByLabel('2 tarefas')).toBeVisible()
    await expect(column(page, 'Em andamento').getByTestId('task-card')).toContainText('Atrasada')

    await page.getByRole('group', { name: 'Filtrar tarefas' }).getByRole('button', { name: 'Minhas' }).click()
    expect(calls.some((call) => call.path === '/board' && call.query === '?filter=mine')).toBe(true)
  })

  test('move pelo teclado e anuncia a nova posição', async ({ page, goto }) => {
    const calls = await open(page, goto)
    const card = page.getByRole('listitem', { name: 'Fechar contrato do buffet. A fazer.' })
    await card.focus()
    await page.keyboard.press('Alt+ArrowRight')

    await expect(page.getByTestId('tasks-announcer')).toHaveText('"Fechar contrato do buffet" foi para Em andamento, posição 1 de 2.')
    await expect(column(page, 'Em andamento')).toContainText('Fechar contrato do buffet')
    await expect(page.getByRole('listitem', { name: 'Fechar contrato do buffet. Em andamento.' })).toBeFocused()
    expect(calls.find((call) => call.path === '/t-1/move')?.body).toEqual({ task_column_id: 'c-doing', position: 0 })

    await page.keyboard.press('Alt+ArrowDown')
    await expect(page.getByTestId('tasks-announcer')).toHaveText('"Fechar contrato do buffet" foi para Em andamento, posição 2 de 2.')
  })

  test('arrastar com o mouse; se a API recusa, a tarefa volta e o motivo é anunciado', async ({ page, goto }) => {
    await open(page, goto, 'organizador', (route, call) => {
      if (call.path !== '/t-2/move') return false
      route.fulfill({ status: 422, json: { code: 'validation_failed', message: 'x', errors: { task: ['A tarefa mudou enquanto isso. Atualize o quadro e tente de novo.'] }, request_id: 'r' } })
      return true
    })

    await page.getByRole('listitem', { name: 'Fechar contrato do buffet. A fazer.' }).dragTo(column(page, 'Em revisão'))
    await expect(column(page, 'Em revisão')).toContainText('Fechar contrato do buffet')

    await page.getByRole('listitem', { name: 'Pedir arte dos crachás. A fazer.' }).dragTo(column(page, 'Feito'))
    await expect(page.getByTestId('tasks-announcer')).toContainText('A tarefa voltou para A fazer.')
    await expect(column(page, 'A fazer')).toContainText('Pedir arte dos crachás')
    await expect(column(page, 'Feito')).not.toContainText('Pedir arte dos crachás')
  })

  test('voluntário vê o quadro, move a própria tarefa e não cria nem move a dos outros', async ({ page, goto }) => {
    const calls = await open(page, goto, 'voluntario')
    await expect(page.getByRole('button', { name: 'Nova tarefa' })).toHaveCount(0)

    await page.getByRole('listitem', { name: 'Fechar contrato do buffet. A fazer.' }).focus()
    await page.keyboard.press('Alt+ArrowRight')
    await expect(page.getByTestId('tasks-announcer')).toHaveText('Você não pode mover esta tarefa.')

    await page.getByRole('listitem', { name: 'Pedir arte dos crachás. A fazer.' }).focus()
    await page.keyboard.press('Alt+ArrowRight')
    await expect(column(page, 'Em andamento')).toContainText('Pedir arte dos crachás')
    expect(calls.filter((call) => call.path.endsWith('/move')).map((call) => call.path)).toEqual(['/t-2/move'])
  })
})
