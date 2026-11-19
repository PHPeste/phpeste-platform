import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page, Route } from '@playwright/test'

const session = { user: { id: 'u-me', name: 'Rita Organiza', email: 'rita@php-pb.net', avatarUrl: null }, token: 'e2e-token' }
const edition = (role: string) => ({
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHP-PB', slug: 'php-pb' },
  status: 'sales_open', status_label: 'Vendas abertas', starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role,
})
const money = (amount: number) => ({ amount, currency: 'BRL' })
const item = (id: string, name: string) => ({
  id, ticket_type_id: 'tt-1', ticket_type_name: 'Inteira', batch_name: '1º lote', unit_price: money(15000), discount: money(0), fee: money(0), total: money(15000),
  attendee: { name, email: `${name.split(' ')[0]!.toLowerCase()}@exemplo.com` }, cancelled_at: null, external_code: null,
})
const paidOrder = {
  id: '0e1c6d1a-0000-4000-8000-000000000042', number: 42, status: 'paid', status_label: 'Pago', source: 'checkout', external: null,
  buyer: { name: 'Ana Souza', email: 'ana@exemplo.com', document: '***.982.247-**' },
  subtotal: money(30000), discount: money(0), fee: money(0), total: money(30000), refunded: money(0), gateway_fee: money(1497),
  requires_payment: true, coupon_code: null, expires_at: null, paid_at: '2027-06-10T12:00:00Z', created_at: '2027-06-10T11:50:00Z',
  items: [item('i-1', 'Ana Souza'), item('i-2', 'Bia Lima')],
}
const awaitingOrder = {
  ...paidOrder, id: '0e1c6d1a-0000-4000-8000-000000000043', number: 43, status: 'awaiting_payment', status_label: 'Aguardando pagamento',
  buyer: { name: 'Caio Reis', email: 'caio@exemplo.com', document: null }, paid_at: null, expires_at: new Date(Date.now() + 20 * 60_000).toISOString(), items: [item('i-3', 'Caio Reis')],
  total: money(15000), subtotal: money(15000),
}
const history = [{ id: 'created', kind: 'created', title: 'Pedido criado', detail: null, actor: null, at: '2027-06-10T11:50:00Z' }]

interface Calls { method: string, path: string, query: string, body: unknown }

async function open(page: Page, goto: (url: string, options: { waitUntil: 'hydration' }) => Promise<unknown>, role = 'organizador', handle: (route: Route, call: Calls) => Promise<boolean> | boolean = () => false) {
  const calls: Calls[] = []
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition(role)] } }))
  await page.route('**/api/v1/editions/ed-27/ticketing/orders**', async (route) => {
    const url = new URL(route.request().url())
    const call = { method: route.request().method(), path: url.pathname.replace('/api/v1/editions/ed-27/ticketing/orders', ''), query: url.search, body: route.request().postDataJSON() }
    calls.push(call)
    if (await handle(route, call)) return
    const order = call.path.includes(awaitingOrder.id) ? awaitingOrder : paidOrder
    if (call.path === '' && url.searchParams.get('status') === 'expired') return route.fulfill({ status: 200, json: { data: [], meta: { current_page: 1, last_page: 1, total: 0 } } })
    if (call.path === '') return route.fulfill({ status: 200, json: { data: [paidOrder, awaitingOrder], meta: { current_page: 1, last_page: 1, total: 2, summary: { paid: 1, awaiting: 1, awaiting_expiring_today: 1, refunds: 0, refunded_cents: 0, gross_cents: 30000, gateway_fee_cents: 1497 } } } })
    if (call.path === '/export') return route.fulfill({ status: 200, headers: { 'Content-Type': 'text/csv' }, body: '﻿Pedido;Situação\n#42;Pago\n' })
    if (call.path.endsWith('/history')) return route.fulfill({ status: 200, json: { data: history } })
    if (call.path.endsWith('/refunds') && call.method === 'GET') return route.fulfill({ status: 200, json: { data: [] } })
    if (call.method === 'GET') {
      const payment = order === awaitingOrder ? { method: 'pix', method_label: 'Pix', status: 'pending', status_label: 'Aguardando pagamento', installments: 1, expires_at: null, paid_at: null, failure_reason: null } : null
      return route.fulfill({ status: 200, json: { data: order, meta: { payment } } })
    }
    return route.fulfill({ status: 200, json: { data: order } })
  })
  await goto('/pedidos', { waitUntil: 'hydration' })
  await expect(page.getByRole('heading', { name: 'Pedidos', level: 1 })).toBeVisible()
  return calls
}

test.describe('Pedidos', () => {
  test('lista os pedidos e filtra pela situação, com estado vazio para o filtro', async ({ page, goto }) => {
    const calls = await open(page, goto)

    await expect(page.getByRole('row', { name: /Ana Souza/ })).toContainText('R$ 300,00')
    await expect(page.getByRole('row', { name: /Caio Reis/ })).toContainText('Aguardando pagamento')
    await expect(page.getByRole('row', { name: /Ana Souza/ })).toContainText('2× Inteira')
    await expect(page.getByText('Mostrando 2 de 2 pedidos')).toBeVisible()
    const summary = page.getByTestId('orders-summary')
    await expect(summary).toContainText('Aguardando pagamento')
    await expect(summary).toContainText('1 expiram hoje')
    await expect(summary).toContainText('R$ 14,97')
    await expect(summary).toContainText('5,0% do bruto')

    await page.getByRole('group', { name: 'Filtrar pela situação' }).getByRole('button', { name: 'Expirados' }).click()
    await expect(page.getByText('Nenhum pedido com esse filtro')).toBeVisible()
    expect(calls.some((call) => call.path === '' && call.query.includes('status=expired'))).toBe(true)

    await page.getByRole('button', { name: 'Limpar filtros' }).click()
    await expect(page.getByRole('row', { name: /Ana Souza/ })).toBeVisible()
  })

  test('reembolso parcial avisa da taxa, exige confirmação explícita e mostra o resultado do estorno', async ({ page, goto }) => {
    const calls = await open(page, goto, 'organizador', (route, call) => {
      if (call.method !== 'POST' || !call.path.endsWith('/refunds')) return false
      route.fulfill({ status: 201, json: { data: { id: 'r-1', order_id: paidOrder.id, amount: money(5000), status: 'completed', status_label: 'Reembolsado', reason: 'event_changed', reason_label: 'Evento cancelado ou alterado', note: null, cancelled_item_ids: ['i-2'], failure_reason: null, requested_by: 'u-me', created_at: '2027-06-11T10:00:00Z', completed_at: '2027-06-11T10:00:00Z' } } })
      return true
    })

    await page.getByRole('button', { name: 'Abrir pedido 42' }).click()
    const drawer = page.getByRole('dialog', { name: 'Pedido #42' })
    await expect(drawer.getByTestId('order-history')).toContainText('Pedido criado')
    await expect(drawer.getByRole('button', { name: 'Marcar como pago' })).toHaveCount(0)
    await drawer.getByRole('button', { name: 'Reembolsar' }).click()

    const modal = page.getByRole('dialog', { name: 'Reembolsar pedido #42' })
    await expect(modal.getByTestId('refund-summary')).toContainText('Ana Souza · 2× Inteira · R$ 300,00 pagos')
    await expect(modal.getByTestId('refund-fee-warning')).toContainText('R$ 14,97')
    await modal.getByLabel('Parcial').check()
    await modal.getByLabel('Valor a devolver').fill('50,00')
    await modal.getByLabel('Motivo').selectOption('event_changed')
    await modal.getByLabel('Cancelar ingressos').check()
    await modal.getByLabel('Ana Souza · Inteira').uncheck()
    await modal.getByRole('button', { name: 'Revisar reembolso' }).click()

    const confirm = page.getByRole('dialog', { name: 'Confirme o reembolso' })
    await expect(confirm).toContainText('devolver R$ 50,00 para Ana Souza')
    await expect(confirm).toContainText('1 ingresso(s) vão ser cancelados.')
    const button = confirm.getByRole('button', { name: 'Estornar R$ 50,00' })
    await expect(button).toBeDisabled()
    await confirm.getByLabel('Conferi o valor e quero estornar').check()
    await button.click()

    await expect(page.getByRole('dialog', { name: 'Resultado do estorno' })).toContainText('O gateway confirmou o estorno de R$ 50,00')
    expect(calls.find((call) => call.method === 'POST')?.body).toEqual({ amount_cents: 5000, reason: 'event_changed', note: null, cancel_tickets: true, item_ids: ['i-2'] })
  })

  test('pedido aguardando: reenviar cobrança e marcar como pago com justificativa', async ({ page, goto }) => {
    const calls = await open(page, goto, 'organizador', (route, call) => {
      if (call.method === 'POST' && call.path.endsWith('/payment-reminder')) {
        route.fulfill({ status: 202, json: { data: { delivery_id: 'd-1' } } })
        return true
      }
      return false
    })

    await page.getByRole('button', { name: 'Abrir pedido 43' }).click()
    const drawer = page.getByRole('dialog', { name: 'Pedido #43' })
    await expect(drawer.getByTestId('order-payment')).toContainText('Pix')
    await expect(drawer.getByRole('button', { name: 'Reembolsar' })).toHaveCount(0)

    await drawer.getByRole('button', { name: 'Reenviar cobrança' }).click()
    await expect(page.getByText('Cobrança reenviada para caio@exemplo.com.')).toBeVisible()

    await drawer.getByRole('button', { name: 'Marcar como pago' }).click()
    const modal = page.getByRole('dialog', { name: 'Marcar o pedido #43 como pago' })
    await modal.getByRole('button', { name: 'Confirmar pagamento' }).click()
    await expect(modal).toContainText('pelo menos 10 caracteres')

    await modal.getByLabel('Justificativa').fill('Pix direto para a conta da comunidade, comprovante no drive')
    await modal.getByRole('button', { name: 'Confirmar pagamento' }).click()
    await expect(page.getByText('Pedido marcado como pago. Os ingressos foram enviados.')).toBeVisible()
    expect(calls.find((call) => call.path.endsWith('/mark-paid'))?.body).toEqual({ justification: 'Pix direto para a conta da comunidade, comprovante no drive' })
  })

  test('o financeiro reembolsa, mas não cobra, não marca como pago nem cancela', async ({ page, goto }) => {
    await open(page, goto, 'financeiro')

    await page.getByRole('button', { name: 'Abrir pedido 43' }).click()
    const drawer = page.getByRole('dialog', { name: 'Pedido #43' })
    await expect(drawer.getByTestId('order-detail')).toBeVisible()
    await expect(drawer.getByRole('button', { name: 'Reenviar cobrança' })).toHaveCount(0)
    await expect(drawer.getByRole('button', { name: 'Marcar como pago' })).toHaveCount(0)
    await expect(drawer.getByRole('button', { name: 'Cancelar pedido' })).toHaveCount(0)
    await drawer.getByRole('button', { name: 'Fechar' }).first().click()

    await page.getByRole('button', { name: 'Abrir pedido 42' }).click()
    await expect(page.getByRole('dialog', { name: 'Pedido #42' }).getByRole('button', { name: 'Reembolsar' })).toBeVisible()
  })

  test('exporta o CSV com o filtro atual', async ({ page, goto }) => {
    const calls = await open(page, goto)

    const download = page.waitForEvent('download')
    await page.getByRole('button', { name: 'Exportar CSV' }).click()
    expect((await download).suggestedFilename()).toMatch(/^pedidos-\d{4}-\d{2}-\d{2}\.csv$/)
    await expect(page.getByText('Planilha baixada.')).toBeVisible()
    expect(calls.some((call) => call.path === '/export')).toBe(true)
  })
})
