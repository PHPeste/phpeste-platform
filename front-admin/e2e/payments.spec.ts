import { expect, test } from '@nuxt/test-utils/playwright'
import type { Page, Route } from '@playwright/test'

const session = { user: { id: 'u-me', name: 'Rita Organiza', email: 'rita@php-pb.net', avatarUrl: null }, token: 'e2e-token' }
const edition = (role: string) => ({
  id: 'ed-27', slug: '2027', name: 'PHPeste 2027', community_id: 'c1', community: { id: 'c1', name: 'PHP-PB', slug: 'php-pb' },
  status: 'cfp_open', status_label: 'CFP aberto', starts_on: '2027-10-08', ends_on: '2027-10-09', city: 'João Pessoa', state: 'PB', year: 2027, site_url: null, role,
})
const drivers = [{ key: 'asaas', label: 'Asaas', needs_public_key: false }, { key: 'mercadopago', label: 'Mercado Pago', needs_public_key: true }]
const account = (overrides: Record<string, unknown> = {}) => ({
  id: 'a-1', driver: 'asaas', environment: 'sandbox', environment_label: 'Sandbox (testes)', credentials_hint: '••••abcd',
  holder: { type: 'individual', type_label: 'Pessoa física (CPF)', name: 'Fulana de Tal', document: '***.982.247-**' },
  methods: [{ key: 'pix', label: 'Pix' }, { key: 'card', label: 'Cartão de crédito' }], max_installments: 3, pix_expiration_minutes: 30,
  active: true, ready: false, verified_at: null, webhook_verified_at: null, webhook_rejections: { count: 0, last_at: null },
  retired_at: null, created_at: '2027-06-01T12:00:00Z', webhook_url: 'http://localhost/api/v1/webhooks/payments/abc', last_event_at: null,
  ...overrides,
})
const failedEvent = { id: 'e-1', payment_account_id: 'a-1', driver: 'asaas', event_id: 'evt_9', type: 'PAYMENT_RECEIVED', gateway_charge_id: 'pay_1', status: 'failed', status_label: 'Com falha', result: 'Cobrança não encontrada', attempts: 5, can_reprocess: true, next_attempt_at: null, processed_at: null, received_at: '2027-06-02T12:00:00Z' }

interface Call { method: string, path: string, body: unknown }

async function open(page: Page, goto: (url: string, options: { waitUntil: 'hydration' }) => Promise<unknown>, state: { active: unknown, previous?: unknown[] }, handle: (route: Route, call: Call) => boolean | Promise<boolean> = () => false, role = 'organizador') {
  const calls: Call[] = []
  await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
  await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition(role)] } }))
  await page.route('**/api/v1/editions/ed-27/payments/**', async (route) => {
    const url = new URL(route.request().url())
    const call = { method: route.request().method(), path: url.pathname.replace('/api/v1/editions/ed-27/payments', '') + url.search, body: route.request().postDataJSON() }
    calls.push(call)
    if (await handle(route, call)) return
    if (call.path.startsWith('/webhook-events') && call.method === 'GET') return route.fulfill({ status: 200, json: { data: state.active ? [failedEvent] : [], meta: { current_page: 1, last_page: 1, total: 1 } } })
    if (call.path === '/accounts') return route.fulfill({ status: 200, json: { data: { active: state.active, previous: state.previous ?? [], drivers } } })
    return route.fulfill({ status: 404, json: { message: 'não simulado' } })
  })
  await goto('/pagamentos', { waitUntil: 'hydration' })
  return calls
}

test.describe('Pagamentos', () => {
  test('sem gateway: conecta com chaves como senha, orientação de CPF e prévia do recebedor', async ({ page, goto }) => {
    const state: { active: unknown } = { active: null }
    const calls = await open(page, goto, state, (route, call) => {
      if (call.method !== 'POST' || call.path !== '/accounts') return false
      state.active = account()
      route.fulfill({ status: 201, json: { data: account() } })
      return true
    })

    await expect(page.getByRole('heading', { name: 'Nenhum gateway conectado' })).toBeVisible()
    await page.getByRole('button', { name: 'Conectar gateway' }).click()
    const drawer = page.getByRole('dialog', { name: 'Conectar gateway' })

    await expect(drawer.getByLabel('Chave de API')).toHaveAttribute('type', 'password')
    await expect(drawer.getByLabel('Segredo do webhook')).toHaveAttribute('type', 'password')
    await expect(drawer.getByTestId('holder-guidance')).toContainText('carnê-leão')

    await expect(drawer.getByRole('button', { name: 'Asaas' })).toHaveAttribute('aria-pressed', 'true')
    await drawer.getByLabel('Chave de API').fill('$aact_hmlg_chave_abcd')
    await drawer.getByLabel('Segredo do webhook').fill('segredo-do-webhook')
    await drawer.getByLabel('Nome exibido no checkout').fill('Fulana de Tal')
    await drawer.getByLabel('CPF do titular').fill('529.982.247-25')
    await drawer.getByLabel('Parcelamento máximo no cartão').selectOption('3')
    await expect(drawer.getByTestId('receiver-preview')).toContainText('Fulana de Tal')
    await expect(drawer.getByTestId('receiver-preview')).toContainText('Cartão de crédito (até 3x)')
    await drawer.getByRole('button', { name: 'Salvar e conectar' }).click()

    await expect(page.getByText('Gateway conectado. Agora teste a conexão.')).toBeVisible()
    await expect(page.getByTestId('account')).toContainText('••••abcd')
    await expect(page.getByTestId('methods')).toContainText('Pix, expira em 30 minutos')
    await expect(page.getByTestId('methods')).toContainText('Cartão de crédito, até 3x')
    await expect(page.getByTestId('account')).not.toContainText('chave_abcd')
    expect(calls.find((call) => call.method === 'POST')?.body).toMatchObject({ driver: 'asaas', environment: 'sandbox', api_key: '$aact_hmlg_chave_abcd', holder_type: 'individual', methods: ['pix', 'card'], max_installments: 3, confirm: false })
  })

  test('testar conexão mostra o erro do gateway e depois marca a etapa como feita', async ({ page, goto }) => {
    let attempt = 0
    await open(page, goto, { active: account() }, (route, call) => {
      if (call.method !== 'POST' || !call.path.endsWith('/verify')) return false
      attempt += 1
      if (attempt === 1) route.fulfill({ status: 422, json: { code: 'validation_failed', message: 'O gateway recusou a chave de API.', errors: { api_key: ['O gateway recusou a chave de API.'] } } })
      else route.fulfill({ status: 200, json: { data: account({ verified_at: '2027-06-02T12:00:00Z' }) } })
      return true
    })

    await expect(page.getByTestId('gateway-status')).toHaveText('Não testado')
    await expect(page.getByTestId('last-event')).toContainText('Nenhum evento recebido')
    await expect(page.getByLabel('Cole este endereço no painel do gateway')).toHaveValue('http://localhost/api/v1/webhooks/payments/abc')

    await page.getByRole('button', { name: 'Testar conexão' }).click()
    await expect(page.getByRole('alert').filter({ hasText: 'O gateway recusou a chave de API.' })).toBeVisible()

    await page.getByRole('button', { name: 'Testar conexão' }).click()
    await expect(page.getByText('Conexão ok: o gateway aceitou as chaves.')).toBeVisible()
    await expect(page.getByTestId('step-verify')).toContainText('O gateway aceitou as chaves')
    await expect(page.getByTestId('gateway-status')).toHaveText('Falta o evento de teste')
  })

  test('trocar de conta com pedidos pagos avisa e pede confirmação', async ({ page, goto }) => {
    const ready = account({ ready: true, verified_at: '2027-06-02T12:00:00Z', webhook_verified_at: '2027-06-02T12:05:00Z', last_event_at: '2027-06-09T18:00:00Z' })
    const calls = await open(page, goto, { active: ready }, (route, call) => {
      if (call.method !== 'POST' || call.path !== '/accounts') return false
      const body = call.body as { confirm: boolean }
      if (!body.confirm) route.fulfill({ status: 422, json: { code: 'validation_failed', message: 'confirm', errors: { confirm: ['Esta conta já recebeu 3 pedidos pagos. A conta atual fica guardada só para reembolsos desses pedidos, e as próximas vendas caem na conta nova. Confirme para trocar.'] } } })
      else route.fulfill({ status: 201, json: { data: account({ id: 'a-2' }) } })
      return true
    })

    await expect(page.getByTestId('gateway-status')).toHaveText('Pronto para vender')
    await expect(page.getByTestId('last-event')).toContainText('Último evento:')
    await page.getByRole('button', { name: 'Trocar conta ou chaves' }).click()
    const drawer = page.getByRole('dialog', { name: 'Trocar conta do gateway' })
    await expect(drawer.getByTestId('replace-warning')).toContainText('pedidos e reembolsos já feitos continuam nela')
    await expect(drawer.getByLabel('Nome exibido no checkout')).toHaveValue('Fulana de Tal')
    await expect(drawer.getByLabel('Chave de API')).toHaveValue('')

    await drawer.getByLabel('Chave de API').fill('nova_chave_wxyz')
    await drawer.getByLabel('Segredo do webhook').fill('novo-segredo-123')
    await drawer.getByLabel('CPF do titular').fill('529.982.247-25')
    await drawer.getByRole('button', { name: 'Salvar e conectar' }).click()

    const confirm = page.getByRole('dialog', { name: 'Trocar de conta com pedidos pagos?' })
    await expect(confirm).toContainText('Esta conta já recebeu 3 pedidos pagos')
    await confirm.getByRole('button', { name: 'Trocar de conta' }).click()
    await expect(page.getByText('Gateway conectado. Agora teste a conexão.')).toBeVisible()
    expect(calls.filter((call) => call.method === 'POST').map((call) => (call.body as { confirm: boolean }).confirm)).toEqual([false, true])
  })

  test('lista os eventos do gateway, filtra e reprocessa o que falhou', async ({ page, goto }) => {
    const calls = await open(page, goto, { active: account() }, (route, call) => {
      if (call.method !== 'POST' || !call.path.endsWith('/reprocess')) return false
      route.fulfill({ status: 200, json: { data: { ...failedEvent, status: 'processed', status_label: 'Processado', can_reprocess: false, result: null } } })
      return true
    })

    const events = page.getByTestId('webhook-events')
    await expect(events.getByRole('row', { name: /PAYMENT_RECEIVED/ })).toContainText('Com falha')
    await page.getByLabel('Situação do evento').selectOption('failed')
    await expect.poll(() => calls.some((call) => call.path.includes('status=failed'))).toBe(true)

    await events.getByRole('button', { name: 'Reprocessar o evento evt_9' }).click()
    await expect(page.getByText('Evento reprocessado: processado.')).toBeVisible()
    await expect(events.getByRole('row', { name: /PAYMENT_RECEIVED/ })).toContainText('Processado')
  })

  test('o financeiro não vê Pagamentos no menu', async ({ page, goto }) => {
    await page.addInitScript((value) => localStorage.setItem('app.auth', JSON.stringify(value)), session)
    await page.route('**/api/v1/editions', (route) => route.fulfill({ status: 200, json: { data: [edition('financeiro')] } }))
    await page.route('**/api/v1/editions/ed-27/ticketing/orders**', (route) => route.fulfill({ status: 200, json: { data: [], meta: { current_page: 1, last_page: 1, total: 0 } } }))
    await goto('/pedidos', { waitUntil: 'hydration' })
    await expect(page.getByRole('link', { name: 'Pedidos' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'Pagamentos' })).toHaveCount(0)
  })
})
