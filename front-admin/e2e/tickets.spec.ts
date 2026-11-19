import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page, Route } from '@playwright/test'

const session = { user: { id: 'u-me', name: 'Rita Organiza', email: 'rita@php-pb.net', avatarUrl: null }, token: 'e2e-token' }
const edition = (role: string) => ({
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHP-PB', slug: 'php-pb' },
  status: 'sales_open', status_label: 'Vendas abertas', starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role,
})
const batch = (overrides: Record<string, unknown> = {}) => ({
  id: 'b-1', ticket_type_id: 'tt-1', name: '1º lote', position: 0, price: { amount: 15000, currency: 'BRL' }, quantity: 100, allocated: 12, remaining: 88,
  starts_at: null, ends_at: null, on_sell_out: 'next', status: 'on_sale', status_label: 'À venda', ...overrides,
})
const ticketType = {
  id: 'tt-1', name: 'Inteira', description: 'Acesso aos dois dias', visibility: 'public', visibility_label: 'Qualquer pessoa', access_code: null, min_per_order: 1, max_per_order: 10,
  show_remaining: true, pass_fee_to_buyer: false, questions: [], position: 0, allocated: 12, quantity: 100, current_batch_id: 'b-1', batches: [batch()],
}
const meta = (ready: boolean) => ({ gateway_ready: ready, capacity: 400, allocated: 12, remaining: 388, reservation_minutes: 30, max_tickets_per_buyer: null, gateway_fee_basis_points: 499 })

async function open(page: Page, goto: (url: string, options: { waitUntil: 'hydration' }) => Promise<unknown>, role: string, ready: boolean, onRequest: (route: Route, method: string, path: string) => Promise<void> | void = () => {}) {
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition(role)] } }))
  await page.route('**/api/v1/editions/ed-27/ticketing/**', async (route) => {
    const method = route.request().method()
    const path = new URL(route.request().url()).pathname.replace('/api/v1/editions/ed-27/ticketing', '')
    await onRequest(route, method, path)
    if (path === '/ticket-types' && method === 'GET') return route.fulfill({ status: 200, json: { data: [ticketType], meta: meta(ready) } })
    if (path === '/coupons' && method === 'GET') return route.fulfill({ status: 200, json: { data: [] } })
    return route.fulfill({ status: 200, json: { data: {} } })
  })
  await goto('/ingressos', { waitUntil: 'hydration' })
  await expect(page.getByRole('heading', { name: 'Ingressos', level: 1 })).toBeVisible()
}

test.describe('Ingressos', () => {
  test('lista tipos e lotes com o líquido depois da taxa e avisa que as vendas estão pausadas', async ({ page, goto }) => {
    await open(page, goto, 'organizador', false)

    await expect(page.getByTestId('sales-paused')).toContainText('Vendas pausadas até o teste do gateway')
    await expect(page.getByRole('link', { name: 'Ir para Pagamentos' })).toHaveAttribute('href', '/pagamentos')
    await expect(page.getByText('12 de 400 lugares da edição vendidos ou reservados')).toBeVisible()

    const row = page.getByRole('row', { name: /1º lote/ })
    await expect(row).toContainText('R$ 150,00')
    await expect(row).toContainText('R$ 142,51')
    await expect(row).toContainText('12 / 100')
    await expect(row).toContainText('À venda')
  })

  test('editar o preço de lote com vendas avisa que pedidos existentes não mudam e manda centavos', async ({ page, goto }) => {
    const bodies: unknown[] = []
    await open(page, goto, 'organizador', true, (route, method, path) => {
      if (method === 'PATCH' && path === '/ticket-types/tt-1/batches/b-1') bodies.push(route.request().postDataJSON())
    })

    await expect(page.getByTestId('sales-paused')).toHaveCount(0)
    await page.getByRole('button', { name: 'Editar 1º lote de Inteira' }).click()
    const dialog = page.getByRole('dialog', { name: 'Editar 1º lote' })
    await dialog.getByLabel('Preço').fill('170,00')

    await expect(dialog.getByTestId('price-change-warning')).toContainText('pedidos existentes não mudam')
    await expect(dialog.getByTestId('net-calculation')).toContainText('R$ 161,51')

    await dialog.getByRole('button', { name: 'Salvar lote' }).click()
    await expect(page.getByText('Lote "1º lote" salvo.')).toBeVisible()
    expect(bodies[0]).toMatchObject({ price_cents: 17000, quantity: 100 })
  })

  test('cria tipo com pergunta do checkout vendo a prévia do cartão', async ({ page, goto }) => {
    const bodies: unknown[] = []
    await open(page, goto, 'organizador', true, (route, method, path) => {
      if (method === 'POST' && path === '/ticket-types') bodies.push(route.request().postDataJSON())
    })

    await page.getByRole('button', { name: 'Novo tipo de ingresso' }).click()
    const drawer = page.getByRole('dialog', { name: 'Novo tipo de ingresso' })
    await drawer.getByLabel('Nome do ingresso').fill('Estudante')
    await drawer.getByLabel('Quem pode comprar').selectOption('code')
    await drawer.getByLabel('Código de acesso').fill('UFPB2027')
    await drawer.getByRole('button', { name: 'Adicionar pergunta' }).click()
    await drawer.getByLabel('Texto da pergunta').fill('Curso')

    await expect(drawer.getByTestId('ticket-preview')).toContainText('Estudante')
    await expect(drawer.getByTestId('ticket-preview')).toContainText('Só com código')

    await drawer.getByRole('button', { name: 'Salvar ingresso' }).click()
    await expect(page.getByText('Ingresso "Estudante" salvo.')).toBeVisible()
    expect(bodies[0]).toMatchObject({ name: 'Estudante', visibility: 'code', access_code: 'UFPB2027', questions: [{ label: 'Curso', type: 'text', required: false }] })
  })

  test('cria cupom percentual', async ({ page, goto }) => {
    const bodies: unknown[] = []
    await open(page, goto, 'organizador', true, (route, method, path) => {
      if (method === 'POST' && path === '/coupons') bodies.push(route.request().postDataJSON())
    })

    await page.getByRole('tab', { name: 'Cupons' }).click()
    await page.getByRole('button', { name: 'Novo cupom' }).click()
    const dialog = page.getByRole('dialog', { name: 'Novo cupom' })
    await dialog.getByLabel('Código do cupom').fill('comunidade')
    await dialog.getByLabel('Percentual de desconto').fill('20')
    await dialog.getByLabel('Limite de usos').fill('50')
    await dialog.getByRole('button', { name: 'Salvar cupom' }).click()

    await expect(page.getByText('Cupom COMUNIDADE salvo.')).toBeVisible()
    expect(bodies[0]).toMatchObject({ code: 'COMUNIDADE', kind: 'percent', value: 20, max_uses: 50 })
  })

  test('o financeiro vê os ingressos, mas não muda nada', async ({ page, goto }) => {
    await open(page, goto, 'financeiro', true)

    await expect(page.getByRole('row', { name: /1º lote/ })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Novo tipo de ingresso' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Editar 1º lote de Inteira' })).toHaveCount(0)
  })
})
