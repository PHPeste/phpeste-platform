import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page } from '@playwright/test'

const session = { user: { id: 'u-me', name: 'Fábio Finanças', email: 'fabio@php-pb.net', avatarUrl: null }, token: 'e2e-token' }
const edition = (role: string) => ({
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHP-PB', slug: 'php-pb' },
  status: 'sales_open', status_label: 'Vendas abertas', starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role,
})
const summary = (paid: number) => ({
  revenue: {
    tickets: {
      gross: 1_920_000, refunded: 0, fees: 70_000, net: 1_850_000,
      by_account: [{ account: { id: 'acc-1', driver: 'asaas', holder_type: 'cnpj', holder_name: 'Associação PHP-PB', holder_document: '**.***.***/0001-90', retired: false }, orders: 128, gross: 1_920_000, refunded: 0, fees: 70_000, net: 1_850_000, estimated_fees: 0 }],
    },
    sponsors: { received: 150_000, outstanding: 0 },
    total: 2_000_000,
  },
  expenses: { paid, pending: 0, overdue: 0 },
  balance: 2_000_000 - paid,
  forecast: 2_000_000 - paid,
  budget: {
    categories: [{ id: 'cat-local', name: 'Local', budget: 1_800_000, committed: paid, paid, remaining: 1_800_000 - paid, percent_used: Math.floor(paid * 100 / 1_800_000), over_budget: false }],
    budgeted: 1_800_000, committed: paid, paid,
  },
})
const expense = { id: 'e-1', description: 'Sinal do espaço', category: { id: 'cat-local', name: 'Local' }, supplier: null, amount: 900_000, due_on: '2027-07-10', paid_on: '2027-07-09', status: 'paid', status_label: 'Paga', overdue: false, notes: null, receipt: null }

interface Call { method: string, path: string, query: string, body: unknown }

async function open(page: Page, goto: (url: string, options: { waitUntil: 'hydration' }) => Promise<unknown>, role: string) {
  const calls: Call[] = []
  const state = { created: false }
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition(role)] } }))
  await page.route('**/api/v1/editions/ed-27/finance/**', (route) => {
    const request = route.request()
    const url = new URL(request.url())
    const call = { method: request.method(), path: url.pathname.replace('/api/v1/editions/ed-27/finance', ''), query: url.search, body: request.postDataJSON?.() ?? null }
    calls.push(call)
    if (call.path === '/summary') return route.fulfill({ status: 200, json: { data: summary(state.created ? 1_150_000 : 900_000) } })
    if (call.path === '/categories') return route.fulfill({ status: 200, json: { data: [{ id: 'cat-local', name: 'Local', budget: 1_800_000, position: 0 }] } })
    if (call.path === '/suppliers') return route.fulfill({ status: 200, json: { data: [] } })
    if (call.path === '/expenses' && call.method === 'POST') {
      state.created = true
      return route.fulfill({ status: 201, json: { data: { ...expense, id: 'e-2', description: 'Lona do palco', amount: 250_000 } } })
    }
    if (call.path === '/expenses') {
      const data = state.created ? [expense, { ...expense, id: 'e-2', description: 'Lona do palco', amount: 250_000 }] : [expense]
      return route.fulfill({ status: 200, json: { data, totals: { count: data.length, amount: data.reduce((sum, row) => sum + row.amount, 0), paid: 0, pending: 0 } } })
    }
    if (call.path === '/export') return route.fulfill({ status: 200, headers: { 'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }, body: 'PK' })
    return route.fulfill({ status: 404, json: { code: 'not_found', message: 'x', errors: null, request_id: 'r' } })
  })
  await goto('/financas', { waitUntil: 'hydration' })
  return calls
}

test.describe('Finanças', () => {
  test('o financeiro vê indicadores, orçamento com texto da barra e receita por conta, em reais com milhar', async ({ page, goto }) => {
    await open(page, goto, 'financeiro')
    await expect(page.getByRole('heading', { name: 'Finanças', level: 1 })).toBeVisible()

    const kpis = page.getByTestId('finance-kpis')
    await expect(kpis).toContainText('R$ 20.000')
    await expect(kpis).toContainText('R$ 9.000')
    await expect(kpis).toContainText('R$ 11.000')
    await expect(page.getByRole('meter', { name: 'Local' })).toHaveAttribute('aria-valuetext', /R\$\s9\.000 de R\$\s18\.000/)
    await expect(page.getByTestId('finance-revenue')).toContainText('Asaas · Associação PHP-PB')
    await expect(page.getByTestId('finance-revenue')).toContainText('R$ 700')
  })

  test('registra despesa e atualiza orçamento e total da lista', async ({ page, goto }) => {
    const calls = await open(page, goto, 'financeiro')
    await page.getByRole('button', { name: 'Nova despesa' }).first().click()

    const dialog = page.getByRole('dialog', { name: 'Nova despesa' })
    await dialog.getByLabel('Descrição').fill('Lona do palco')
    await dialog.getByLabel('Valor (R$)').fill('2.500')
    await dialog.getByRole('button', { name: 'Salvar' }).click()

    await expect(page.getByRole('row', { name: /Lona do palco/ })).toContainText('R$ 2.500')
    await expect(page.getByTestId('expenses-total')).toContainText('R$ 11.500')
    await expect(page.getByRole('meter', { name: 'Local' })).toHaveAttribute('aria-valuetext', /R\$\s11\.500 de R\$\s18\.000/)
    expect(calls.find((call) => call.method === 'POST' && call.path === '/expenses')?.body).toMatchObject({ amount_cents: 250_000, finance_category_id: 'cat-local', status: 'pending', paid_on: null })
  })

  test('exporta a planilha', async ({ page, goto }) => {
    const calls = await open(page, goto, 'organizador')
    const download = page.waitForEvent('download')
    await page.getByRole('button', { name: 'Exportar planilha' }).click()
    expect((await download).suggestedFilename()).toMatch(/^financas-\d{4}-\d{2}-\d{2}\.xlsx$/)
    expect(calls.some((call) => call.path === '/export' && call.query === '')).toBe(true)
  })

  test('papel sem permissão financeira não abre a tela', async ({ page, goto }) => {
    const calls = await open(page, goto, 'avaliador')
    await expect(page.getByRole('heading', { name: 'Sem permissão' })).toBeVisible()
    expect(calls).toHaveLength(0)
  })
})
